<?php

namespace App\Controller\Expense;

use App\Entity\ExpenseCategory;
use App\Entity\User;
use App\Form\ExpenseCategoryType;
use App\Security\Voter\ExpenseVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\Expense\ExpenseCategoryService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses/categories')]
#[IsGranted('ROLE_USER')]
final class ExpenseCategoryController extends AbstractController
{
    public function __construct(
        private readonly ExpenseCategoryService $categoryService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_expense_category_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE_CATEGORIES);

        return $this->render('expense/category/index.html.twig', [
            'categories' => $this->categoryService->search($this->currentUser()),
            'create_form' => $this->buildForm(new ExpenseCategory(), 'app_expense_category_create'),
        ]);
    }

    #[Route('/creer', name: 'app_expense_category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE_CATEGORIES);
        $category = new ExpenseCategory();
        $form = $this->buildForm($category, 'app_expense_category_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->categoryService->create($category, $this->currentUser());

        return $this->jsonResponder->success('La catégorie a été créée.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_expense_category_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(ExpenseCategory $category): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE_CATEGORIES);

        return $this->render('expense/category/_form_modal.html.twig', [
            'form' => $this->buildForm($category, 'app_expense_category_edit', ['id' => $category->getId()]),
            'title' => 'Modifier une catégorie',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_expense_category_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(ExpenseCategory $category, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::MANAGE_CATEGORIES);
        $form = $this->buildForm($category, 'app_expense_category_edit', ['id' => $category->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->categoryService->update($category, $this->currentUser());

        return $this->jsonResponder->success('La catégorie a été modifiée.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_expense_category_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(ExpenseCategory $category, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('toggle_expense_category_'.$category->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $active = $this->categoryService->toggle($category, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'La catégorie a été activée.' : 'La catégorie a été désactivée.',
            ['reload' => true],
        );
    }

    /** @param array<string, int|string> $parameters */
    private function buildForm(ExpenseCategory $category, string $route, array $parameters = []): FormInterface
    {
        return $this->createForm(ExpenseCategoryType::class, $category, [
            'action' => $this->generateUrl($route, $parameters),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
