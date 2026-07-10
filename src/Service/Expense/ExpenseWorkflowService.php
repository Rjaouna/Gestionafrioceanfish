<?php

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ExpenseWorkflowService
{
    public function __construct(
        private ExpenseAccessService $access,
        private CashFundService $cashFundService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function submit(Expense $expense, User $actor): void
    {
        if (!$this->access->canSubmit($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense
            ->setStatus(Expense::STATUS_PENDING)
            ->setRefusedReason(null)
            ->setRefusedBy(null);
        $this->entityManager->flush();
    }

    public function validate(Expense $expense, User $actor): void
    {
        if (!$this->access->canValidate($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense
            ->setStatus(Expense::STATUS_VALIDATED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($actor)
            ->setRefusedReason(null)
            ->setRefusedBy(null);
        $this->entityManager->flush();
    }

    public function refuse(Expense $expense, string $reason, User $actor): void
    {
        if (!$this->access->canRefuse($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de refus est obligatoire.');
        }

        $expense
            ->setStatus(Expense::STATUS_REFUSED)
            ->setRefusedReason($reason)
            ->setRefusedBy($actor);
        $this->entityManager->flush();
    }

    public function markAsPaid(Expense $expense, User $actor): void
    {
        if (!$this->access->canMarkAsPaid($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense
            ->setStatus(Expense::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable())
            ->setPaidBy($actor);
        $this->cashFundService->deductPaidExpense($expense, $actor);
        $this->entityManager->flush();
    }

    public function cancel(Expense $expense, User $actor): void
    {
        if (!$this->access->canCancel($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense->setStatus(Expense::STATUS_CANCELLED);
        $this->entityManager->flush();
    }

    public function reactivate(Expense $expense, User $actor): void
    {
        if (!$this->access->canReactivate($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense
            ->setStatus(Expense::STATUS_DRAFT)
            ->setPaidAt(null)
            ->setPaidBy(null)
            ->setValidatedAt(null)
            ->setValidatedBy(null)
            ->setRefusedReason(null)
            ->setRefusedBy(null);
        $this->entityManager->flush();
    }
}
