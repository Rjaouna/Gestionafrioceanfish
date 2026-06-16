<?php

namespace App\Service\Maintenance;

use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class MaintenanceAccessService
{
    public function __construct(private SecurityAccessService $securityAccess)
    {
    }

    public function canAccess(User $user): bool
    {
        return $user->isActive() && $this->securityAccess->canAccessModule($user, 'maintenance');
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canEdit(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canArchive(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canDelete(User $user): bool
    {
        return $this->securityAccess->isSuperAdmin($user);
    }

    public function canChangeStatus(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canAssignIntervenant(User $user): bool
    {
        return $this->canAccess($user);
    }
}
