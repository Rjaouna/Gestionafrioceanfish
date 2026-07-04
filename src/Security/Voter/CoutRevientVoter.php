<?php

namespace App\Security\Voter;

use App\Entity\CoutRevient;
use App\Entity\User;
use App\Service\CoutRevient\CoutRevientPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class CoutRevientVoter extends Voter
{
    public const VIEW = 'COUT_REVIENT_VIEW';
    public const CREATE = 'COUT_REVIENT_CREATE';
    public const EDIT = 'COUT_REVIENT_EDIT';
    public const DELETE = 'COUT_REVIENT_DELETE';
    public const VALIDATE = 'COUT_REVIENT_VALIDATE';
    public const DUPLICATE = 'COUT_REVIENT_DUPLICATE';
    public const EXPORT = 'COUT_REVIENT_EXPORT';

    public function __construct(private readonly CoutRevientPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::VALIDATE,
            self::DUPLICATE,
            self::EXPORT,
        ], true) && ($subject === null || $subject instanceof CoutRevient);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::VIEW => $subject instanceof CoutRevient && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof CoutRevient && $this->permission->canEdit($user, $subject),
            self::DELETE => $subject instanceof CoutRevient && $this->permission->canDelete($user, $subject),
            self::VALIDATE => $subject instanceof CoutRevient && $this->permission->canValidate($user, $subject),
            self::DUPLICATE => $subject instanceof CoutRevient && $this->permission->canDuplicate($user, $subject),
            self::EXPORT => $this->permission->canExport($user, $subject instanceof CoutRevient ? $subject : null),
            default => false,
        };
    }
}
