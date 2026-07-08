<?php

namespace App\Security\Voter;

use App\Entity\FishYieldStudy;
use App\Entity\User;
use App\Service\FishYieldStudy\FishYieldStudyPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FishYieldStudyVoter extends Voter
{
    public const VIEW = 'FISH_YIELD_STUDY_VIEW';
    public const CREATE = 'FISH_YIELD_STUDY_CREATE';
    public const EDIT = 'FISH_YIELD_STUDY_EDIT';
    public const DELETE = 'FISH_YIELD_STUDY_DELETE';
    public const PRINT = 'FISH_YIELD_STUDY_PRINT';

    public function __construct(private readonly FishYieldStudyPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::PRINT,
        ], true) && ($subject === null || $subject instanceof FishYieldStudy);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::PRINT => $this->permission->canPrint($user, $subject instanceof FishYieldStudy ? $subject : null),
            self::VIEW => $subject instanceof FishYieldStudy && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof FishYieldStudy && $this->permission->canEdit($user, $subject),
            self::DELETE => $subject instanceof FishYieldStudy && $this->permission->canDelete($user, $subject),
            default => false,
        };
    }
}
