<?php

namespace App\Security\Voter;

use App\Entity\Intervention;
use App\Entity\User;
use App\Service\Maintenance\MaintenanceAccessService;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class InterventionVoter extends Voter
{
    public const VIEW = 'INTERVENTION_VIEW';
    public const CREATE = 'INTERVENTION_CREATE';
    public const EDIT = 'INTERVENTION_EDIT';
    public const ARCHIVE = 'INTERVENTION_ARCHIVE';
    public const DELETE = 'INTERVENTION_DELETE';
    public const ASSIGN_INTERVENANT = 'INTERVENTION_ASSIGN_INTERVENANT';
    public const CHANGE_STATUS = 'INTERVENTION_CHANGE_STATUS';
    public const CLOSE = 'INTERVENTION_CLOSE';
    public const SHARE = 'INTERVENTION_SHARE';

    public function __construct(
        private readonly MaintenanceAccessService $access,
        private readonly MaintenanceShareService $shareService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::ARCHIVE,
            self::DELETE,
            self::ASSIGN_INTERVENANT,
            self::CHANGE_STATUS,
            self::CLOSE,
            self::SHARE,
        ], true)
            && ($subject === null || $subject instanceof Intervention);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject instanceof Intervention && $this->canView($user, $subject),
            self::CREATE => $this->access->canCreate($user),
            self::EDIT => $subject instanceof Intervention && $this->canView($user, $subject) && $this->access->canEdit($user),
            self::ARCHIVE => $subject instanceof Intervention && $this->canView($user, $subject) && $this->access->canArchive($user),
            self::DELETE => $subject instanceof Intervention && !$subject->isDeleted() && $this->access->canDelete($user),
            self::ASSIGN_INTERVENANT => $subject instanceof Intervention && $this->canView($user, $subject) && $this->access->canAssignIntervenant($user),
            self::CHANGE_STATUS, self::CLOSE => $subject instanceof Intervention && $this->canView($user, $subject) && $this->access->canChangeStatus($user),
            self::SHARE => $subject instanceof Intervention && $this->shareService->canShareObject($user, $subject),
            default => false,
        };
    }

    private function canView(User $user, Intervention $intervention): bool
    {
        return !$intervention->isDeleted()
            && $this->access->canAccess($user)
            && $this->shareService->canViewObject($user, $intervention);
    }
}
