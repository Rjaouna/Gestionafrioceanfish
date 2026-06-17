<?php

namespace App\Security\Voter;

use App\Entity\MaintenanceContract;
use App\Entity\User;
use App\Service\Maintenance\MaintenanceAccessService;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MaintenanceContractVoter extends Voter
{
    public const VIEW = 'MAINTENANCE_CONTRACT_VIEW';
    public const CREATE = 'MAINTENANCE_CONTRACT_CREATE';
    public const EDIT = 'MAINTENANCE_CONTRACT_EDIT';
    public const ARCHIVE = 'MAINTENANCE_CONTRACT_ARCHIVE';
    public const DELETE = 'MAINTENANCE_CONTRACT_DELETE';
    public const SHARE = 'MAINTENANCE_CONTRACT_SHARE';

    public function __construct(
        private readonly MaintenanceAccessService $access,
        private readonly MaintenanceShareService $shareService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::ARCHIVE, self::DELETE, self::SHARE], true)
            && ($subject === null || $subject instanceof MaintenanceContract);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject instanceof MaintenanceContract && $this->canView($user, $subject),
            self::CREATE => $this->access->canCreate($user),
            self::EDIT => $subject instanceof MaintenanceContract && $this->canView($user, $subject) && $this->access->canEdit($user),
            self::ARCHIVE => $subject instanceof MaintenanceContract && $this->canView($user, $subject) && $this->access->canArchive($user),
            self::DELETE => $subject instanceof MaintenanceContract && !$subject->isDeleted() && $this->access->canDelete($user),
            self::SHARE => $subject instanceof MaintenanceContract && $this->shareService->canShareObject($user, $subject),
            default => false,
        };
    }

    private function canView(User $user, MaintenanceContract $contract): bool
    {
        return !$contract->isDeleted()
            && $this->access->canAccess($user)
            && $this->shareService->canViewObject($user, $contract);
    }
}
