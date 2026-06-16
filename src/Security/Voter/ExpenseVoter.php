<?php

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use App\Entity\User;
use App\Service\Expense\ExpenseAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExpenseVoter extends Voter
{
    public const VIEW = 'EXPENSE_VIEW';
    public const CREATE = 'EXPENSE_CREATE';
    public const EDIT = 'EXPENSE_EDIT';
    public const DELETE = 'EXPENSE_DELETE';
    public const ARCHIVE = 'EXPENSE_ARCHIVE';
    public const VALIDATE = 'EXPENSE_VALIDATE';
    public const REFUSE = 'EXPENSE_REFUSE';
    public const MARK_AS_PAID = 'EXPENSE_MARK_AS_PAID';
    public const CANCEL = 'EXPENSE_CANCEL';
    public const SUBMIT = 'EXPENSE_SUBMIT';
    public const DOWNLOAD_DOCUMENT = 'EXPENSE_DOWNLOAD_DOCUMENT';
    public const MANAGE_CATEGORIES = 'EXPENSE_MANAGE_CATEGORIES';

    public function __construct(private readonly ExpenseAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::ARCHIVE,
            self::VALIDATE,
            self::REFUSE,
            self::MARK_AS_PAID,
            self::CANCEL,
            self::SUBMIT,
            self::DOWNLOAD_DOCUMENT,
            self::MANAGE_CATEGORIES,
        ], true) && ($subject === null || $subject instanceof Expense || $subject instanceof ExpenseDocument);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->access->canCreate($user),
            self::MANAGE_CATEGORIES => $this->access->canManageCategories($user),
            self::VIEW => $subject instanceof Expense && $this->access->canView($user, $subject),
            self::EDIT => $subject instanceof Expense && $this->access->canEdit($user, $subject),
            self::DELETE => $subject instanceof Expense && $this->access->canDelete($user, $subject),
            self::ARCHIVE => $subject instanceof Expense && $this->access->canArchive($user, $subject),
            self::VALIDATE => $subject instanceof Expense && $this->access->canValidate($user, $subject),
            self::REFUSE => $subject instanceof Expense && $this->access->canRefuse($user, $subject),
            self::MARK_AS_PAID => $subject instanceof Expense && $this->access->canMarkAsPaid($user, $subject),
            self::CANCEL => $subject instanceof Expense && $this->access->canCancel($user, $subject),
            self::SUBMIT => $subject instanceof Expense && $this->access->canSubmit($user, $subject),
            self::DOWNLOAD_DOCUMENT => $subject instanceof ExpenseDocument && $this->access->canDownloadDocument($user, $subject),
            default => false,
        };
    }
}
