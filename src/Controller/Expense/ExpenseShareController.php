<?php

namespace App\Controller\Expense;

use App\Entity\Expense;
use App\Entity\User;
use App\Security\Voter\ExpenseVoter;
use App\Service\Expense\ExpenseShareService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses/{id}/partages', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class ExpenseShareController extends AbstractController
{
    public function __construct(
        private readonly ExpenseShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_expense_share_modal', methods: ['GET'])]
    public function modal(Expense $expense): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::SHARE, $expense);

        return $this->render('expense/expense/_share_modal.html.twig', [
            'expense' => $expense,
            'share_matrix' => $this->shareService->getShareMatrix($expense, $this->currentUser()),
        ]);
    }

    #[Route('', name: 'app_expense_share_save', methods: ['POST'])]
    public function save(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::SHARE, $expense);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('share_expense_'.$expense->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $items = is_array($payload['shares'] ?? null) ? $payload['shares'] : [];
        $this->shareService->synchronize($expense, $items, $this->currentUser());

        return $this->jsonResponder->success('Les partages de la dépense ont été enregistrés.');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
