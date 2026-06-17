<?php

namespace App\Security\Voter;

use App\Entity\Intervenant;
use App\Entity\User;
use App\Service\Maintenance\MaintenanceAccessService;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class IntervenantVoter extends Voter
{
    public const VIEW = 'INTERVENANT_VIEW';
    public const CREATE = 'INTERVENANT_CREATE';
    public const EDIT = 'INTERVENANT_EDIT';
    public const ARCHIVE = 'INTERVENANT_ARCHIVE';
    public const DELETE = 'INTERVENANT_DELETE';
    public const SHARE = 'INTERVENANT_SHARE';

    public function __construct(
        private readonly MaintenanceAccessService $access,
        private readonly MaintenanceShareService $shareService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::ARCHIVE, self::DELETE, self::SHARE], true)
            && ($subject === null || $subject instanceof Intervenant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject instanceof Intervenant && $this->canView($user, $subject),
            self::CREATE => $this->access->canCreate($user),
            self::EDIT => $subject instanceof Intervenant && $this->canView($user, $subject) && $this->access->canEdit($user),
            self::ARCHIVE => $subject instanceof Intervenant && $this->canView($user, $subject) && $this->access->canArchive($user),
            self::DELETE => $subject instanceof Intervenant && !$subject->isDeleted() && $this->access->canDelete($user),
            self::SHARE => $subject instanceof Intervenant && $this->shareService->canShareObject($user, $subject),
            default => false,
        };
    }

    private function canView(User $user, Intervenant $intervenant): bool
    {
        return !$intervenant->isDeleted()
            && $this->access->canAccess($user)
            && $this->shareService->canViewObject($user, $intervenant);
    }
}
