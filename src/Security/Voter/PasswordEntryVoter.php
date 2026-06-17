<?php

namespace App\Security\Voter;

use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Service\SecurityAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class PasswordEntryVoter extends Voter
{
    public const VIEW = 'PASSWORD_VIEW';
    public const EDIT = 'PASSWORD_EDIT';
    public const DELETE = 'PASSWORD_DELETE';
    public const SHARE = 'PASSWORD_SHARE';
    public const EDIT_PASSWORD = 'PASSWORD_EDIT_VALUE';
    public const VALIDATE = 'PASSWORD_VALIDATE';
    public const TOGGLE_STATUS = 'PASSWORD_TOGGLE_STATUS';

    public function __construct(private readonly SecurityAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof PasswordEntry && in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::SHARE,
            self::EDIT_PASSWORD,
            self::VALIDATE,
            self::TOGGLE_STATUS,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof PasswordEntry) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->access->canViewPassword($user, $subject),
            self::EDIT => !$subject->isDeleted() && $this->access->canEditPasswordEntry($user),
            self::DELETE => !$subject->isDeleted() && $this->access->canDeletePasswords($user),
            self::SHARE => $this->access->canSharePassword($user, $subject),
            self::EDIT_PASSWORD => $this->access->canEditPasswordValue($user, $subject),
            self::VALIDATE => $this->access->canValidatePassword($user, $subject),
            self::TOGGLE_STATUS => $this->access->canTogglePasswordStatus($user, $subject),
            default => false,
        };
    }
}
