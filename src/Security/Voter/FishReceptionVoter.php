<?php

namespace App\Security\Voter;

use App\Entity\FishReception;
use App\Entity\User;
use App\Service\FishReception\FishReceptionPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FishReceptionVoter extends Voter
{
    public const VIEW = 'FISH_RECEPTION_VIEW';
    public const CREATE = 'FISH_RECEPTION_CREATE';
    public const EDIT = 'FISH_RECEPTION_EDIT';
    public const DELETE = 'FISH_RECEPTION_DELETE';
    public const TRANSITION = 'FISH_RECEPTION_TRANSITION';

    public function __construct(private readonly FishReceptionPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::TRANSITION,
        ], true) && ($subject === null || $subject instanceof FishReception);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::VIEW => $subject instanceof FishReception && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof FishReception && $this->permission->canEdit($user, $subject),
            self::DELETE => $subject instanceof FishReception && $this->permission->canDelete($user, $subject),
            self::TRANSITION => $subject instanceof FishReception && $this->permission->canTransition($user, $subject),
            default => false,
        };
    }
}
