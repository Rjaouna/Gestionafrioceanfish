<?php

namespace App\Controller\Expense;

use App\Entity\CashFundTransaction;
use App\Entity\User;
use App\Form\CashFundFundingType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\Expense\CashFundService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses/cagnotte')]
#[IsGranted('ROLE_USER')]
final class CashFundController extends AbstractController
{
    public function __construct(
        private readonly CashFundService $cashFundService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_expense_cash_fund_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filters = $this->filters($request);
        $result = $this->cashFundService->search($filters, max(1, $request->query->getInt('page', 1)));

        return $this->render('expense/cash_fund/index.html.twig', [
            'transactions' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'stats' => $this->cashFundService->stats($filters),
            'type_choices' => CashFundTransaction::TYPE_LABELS,
            'create_form' => $this->buildForm(new CashFundTransaction()),
        ]);
    }

    #[Route('/alimenter', name: 'app_expense_cash_fund_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $transaction = new CashFundTransaction();
        $form = $this->buildForm($transaction);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->cashFundService->fund($transaction, $this->currentUser());

        return $this->jsonResponder->success('Cagnotte alimentee.', ['reload' => true], 201);
    }

    #[Route('/{id}/supprimer', name: 'app_expense_cash_fund_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(CashFundTransaction $transaction, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'expenses');
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_cash_fund_'.$transaction->getId(), (string) ($payload['token'] ?? ''))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $this->cashFundService->deleteFunding($transaction, $this->currentUser());

        return $this->jsonResponder->success('Alimentation supprimee.', ['reload' => true]);
    }

    private function buildForm(CashFundTransaction $transaction): FormInterface
    {
        return $this->createForm(CashFundFundingType::class, $transaction, [
            'action' => $this->generateUrl('app_expense_cash_fund_create'),
        ]);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => trim((string) $request->query->get('type', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
        ];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
