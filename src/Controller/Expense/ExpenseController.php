<?php

namespace App\Controller\Expense;

use App\Entity\Expense;
use App\Entity\User;
use App\Form\ExpenseRefuseType;
use App\Form\ExpenseType;
use App\Security\Voter\ExpenseVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\Expense\ExpenseCategoryService;
use App\Service\Expense\ExpenseDocumentService;
use App\Service\Expense\ExpenseService;
use App\Service\Expense\ExpenseWorkflowService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses')]
#[IsGranted('ROLE_USER')]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpenseCategoryService $categoryService,
        private readonly ExpenseDocumentService $documentService,
        private readonly ExpenseWorkflowService $workflow,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_expense_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $this->denyAccessUnlessGranted(ExpenseVoter::CREATE);

        $filters = $this->filters($request);
        $result = $this->expenseService->search($this->currentUser(), $filters, max(1, $request->query->getInt('page', 1)));

        return $this->render('expense/expense/index.html.twig', [
            'expenses' => $result['items'],
            'pagination' => $result,
            'stats' => $this->expenseService->stats($this->currentUser()),
            'categories' => $this->categoryService->activeCategories($this->currentUser()),
            'status_choices' => Expense::STATUS_LABELS,
            'payment_methods' => Expense::PAYMENT_METHOD_LABELS,
            'filters' => $filters,
            'create_form' => $this->buildForm(new Expense(), 'app_expense_create'),
        ]);
    }

    #[Route('/recherche', name: 'app_expense_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $filters = $this->filters($request);
        $result = $this->expenseService->search($this->currentUser(), $filters, max(1, $request->query->getInt('page', 1)));

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('expense/expense/_expense_grid.html.twig', [
                'expenses' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/creer', name: 'app_expense_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::CREATE);
        $expense = new Expense();
        $form = $this->buildForm($expense, 'app_expense_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->applyCustomCategory($expense, $form);
        $this->expenseService->create($expense, $this->currentUser());
        $this->syncDocument($expense, $form);

        return $this->jsonResponder->success('La dépense a été créée en brouillon.', ['reload' => true], 201);
    }

    #[Route('/{id}/consulter', name: 'app_expense_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(Expense $expense, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VIEW, $expense);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('expense/expense/show.html.twig', ['expense' => $expense]);
        }

        return $this->render('expense/expense/_details_modal.html.twig', ['expense' => $expense]);
    }

    #[Route('/{id}/formulaire', name: 'app_expense_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Expense $expense): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::EDIT, $expense);

        return $this->render('expense/expense/_form_modal.html.twig', [
            'form' => $this->buildForm($expense, 'app_expense_edit', ['id' => $expense->getId()]),
            'expense' => $expense,
            'title' => 'Modifier une dépense',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_expense_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::EDIT, $expense);
        $form = $this->buildForm($expense, 'app_expense_edit', ['id' => $expense->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->applyCustomCategory($expense, $form);
        $this->expenseService->update($expense, $this->currentUser());
        $this->syncDocument($expense, $form);

        return $this->jsonResponder->success('La dépense a été modifiée.', ['reload' => true]);
    }

    #[Route('/{id}/soumettre', name: 'app_expense_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function submit(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::SUBMIT, $expense);
        $this->assertCsrfFromJson($request, 'submit_expense_'.$expense->getId());
        $this->workflow->submit($expense, $this->currentUser());

        return $this->jsonResponder->success('La dépense est en attente de validation.', ['reload' => true]);
    }

    #[Route('/{id}/valider', name: 'app_expense_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validateExpense(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VALIDATE, $expense);
        $this->assertCsrfFromJson($request, 'validate_expense_'.$expense->getId());
        $this->workflow->validate($expense, $this->currentUser());

        return $this->jsonResponder->success('La dépense a été validée.', ['reload' => true]);
    }

    #[Route('/{id}/refus', name: 'app_expense_refuse_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function refuseForm(Expense $expense): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::REFUSE, $expense);

        return $this->render('expense/expense/_refuse_modal.html.twig', [
            'expense' => $expense,
            'form' => $this->createForm(ExpenseRefuseType::class, null, [
                'action' => $this->generateUrl('app_expense_refuse', ['id' => $expense->getId()]),
            ]),
        ]);
    }

    #[Route('/{id}/refuser', name: 'app_expense_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuse(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::REFUSE, $expense);
        $form = $this->createForm(ExpenseRefuseType::class, null, [
            'action' => $this->generateUrl('app_expense_refuse', ['id' => $expense->getId()]),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->workflow->refuse($expense, (string) $form->get('reason')->getData(), $this->currentUser());

        return $this->jsonResponder->success('La dépense a été refusée.', ['reload' => true]);
    }

    #[Route('/{id}/payer', name: 'app_expense_mark_paid', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markPaid(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::MARK_AS_PAID, $expense);
        $this->assertCsrfFromJson($request, 'pay_expense_'.$expense->getId());
        $this->workflow->markAsPaid($expense, $this->currentUser());

        return $this->jsonResponder->success('La dépense a été marquée comme payée.', ['reload' => true]);
    }

    #[Route('/{id}/annuler', name: 'app_expense_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::CANCEL, $expense);
        $this->assertCsrfFromJson($request, 'cancel_expense_'.$expense->getId());
        $this->workflow->cancel($expense, $this->currentUser());

        return $this->jsonResponder->success('La dépense a été annulée.', ['reload' => true]);
    }

    #[Route('/{id}/reactiver', name: 'app_expense_reactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reactivate(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::REACTIVATE, $expense);
        $this->assertCsrfFromJson($request, 'reactivate_expense_'.$expense->getId());
        $this->workflow->reactivate($expense, $this->currentUser());

        return $this->jsonResponder->success('La dépense a été réactivée en brouillon.', ['reload' => true]);
    }

    #[Route('/{id}/archive', name: 'app_expense_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::ARCHIVE, $expense);
        $this->assertCsrfFromJson($request, 'archive_expense_'.$expense->getId());
        $active = $this->expenseService->toggleArchive($expense, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'La dépense a été désarchivée.' : 'La dépense a été archivée.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_expense_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::DELETE, $expense);
        $this->assertCsrfFromJson($request, 'delete_expense_'.$expense->getId());
        $movedToTrash = $this->expenseService->delete($expense, $this->currentUser());
        if ($movedToTrash) {
            return $this->jsonResponder->success('La dépense a été déplacée dans la corbeille.', ['reload' => true]);
        }

        return $this->jsonResponder->success('La dépense a été supprimée.', ['reload' => true]);
    }

    #[Route('/export/csv', name: 'app_expense_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $csv = $this->expenseService->exportCsv($this->currentUser(), $this->filters($request));

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="depenses.csv"',
        ]);
    }

    /** @param array<string, int|string> $parameters */
    private function buildForm(Expense $expense, string $route, array $parameters = []): FormInterface
    {
        return $this->createForm(ExpenseType::class, $expense, [
            'action' => $this->generateUrl($route, $parameters),
            'max_file_size' => $this->documentService->maxFileSize(),
            'allowed_mime_types' => $this->documentService->allowedMimeTypes(),
        ]);
    }

    private function syncDocument(Expense $expense, FormInterface $form): void
    {
        $file = $form->get('documentFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $this->documentService->replacePrimary($expense, $file, (string) $form->get('documentType')->getData(), $this->currentUser());
    }

    private function applyCustomCategory(Expense $expense, FormInterface $form): void
    {
        if ($expense->getCategory() !== null || !$form->has('customCategoryName')) {
            return;
        }

        $categoryName = trim((string) $form->get('customCategoryName')->getData());
        if ($categoryName === '') {
            return;
        }

        $expense->setCategory($this->categoryService->findOrCreateFromName($categoryName, $this->currentUser()));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query->get('q', ''),
            'category' => (string) $request->query->get('category', ''),
            'status' => (string) $request->query->get('status', ''),
            'paymentMethod' => (string) $request->query->get('paymentMethod', ''),
            'dateFrom' => (string) $request->query->get('dateFrom', ''),
            'dateTo' => (string) $request->query->get('dateTo', ''),
            'creator' => (string) $request->query->get('creator', ''),
            'minAmount' => (string) $request->query->get('minAmount', ''),
            'maxAmount' => (string) $request->query->get('maxAmount', ''),
            'active' => (string) $request->query->get('active', 'active'),
        ];
    }

    private function assertCsrfFromJson(Request $request, string $id): void
    {
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid($id, (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
