<?php

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class ExpenseAccessService
{
    public const MODULE_SLUG = 'expenses';

    public function __construct(private SecurityAccessService $security)
    {
    }

    public function canAccess(User $user): bool
    {
        return $user->isActive() && $this->security->canAccessModule($user, self::MODULE_SLUG);
    }

    public function isAdmin(User $user): bool
    {
        return $this->security->isAdmin($user);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->security->isSuperAdmin($user);
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canView(User $user, Expense $expense): bool
    {
        if (!$this->canAccess($user)) {
            return false;
        }

        return $this->isAdmin($user) || $this->isCreator($user, $expense);
    }

    public function canEdit(User $user, Expense $expense): bool
    {
        if (!$this->canView($user, $expense)) {
            return false;
        }

        if ($expense->isPaid() && !$this->isSuperAdmin($user)) {
            return false;
        }

        if (!$expense->isActive() && !$this->isAdmin($user)) {
            return false;
        }

        return $this->isAdmin($user) || $this->isCreator($user, $expense);
    }

    public function canDelete(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense) && $this->isSuperAdmin($user);
    }

    public function canArchive(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense) && $this->isAdmin($user);
    }

    public function canValidate(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense)
            && $this->isAdmin($user)
            && $expense->isActive()
            && $expense->getStatus() === Expense::STATUS_PENDING;
    }

    public function canRefuse(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense)
            && $this->isAdmin($user)
            && $expense->isActive()
            && in_array($expense->getStatus(), [Expense::STATUS_PENDING, Expense::STATUS_VALIDATED], true);
    }

    public function canMarkAsPaid(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense)
            && $this->isAdmin($user)
            && $expense->isActive()
            && $expense->getStatus() === Expense::STATUS_VALIDATED;
    }

    public function canCancel(User $user, Expense $expense): bool
    {
        return $this->canView($user, $expense)
            && ($this->isAdmin($user) || $this->isCreator($user, $expense))
            && $expense->getStatus() !== Expense::STATUS_PAID;
    }

    public function canSubmit(User $user, Expense $expense): bool
    {
        return $this->canEdit($user, $expense)
            && in_array($expense->getStatus(), [Expense::STATUS_DRAFT, Expense::STATUS_REFUSED], true);
    }

    public function canDownloadDocument(User $user, ExpenseDocument $document): bool
    {
        $expense = $document->getExpense();

        return $expense instanceof Expense && $document->isActive() && $this->canView($user, $expense);
    }

    public function canManageCategories(User $user): bool
    {
        return $this->canAccess($user) && $this->isAdmin($user);
    }

    private function isCreator(User $user, Expense $expense): bool
    {
        return $user->getId() !== null && $expense->getCreatedBy()?->getId() === $user->getId();
    }
}
