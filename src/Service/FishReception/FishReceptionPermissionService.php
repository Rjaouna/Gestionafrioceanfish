<?php

namespace App\Service\FishReception;

use App\Entity\FishReception;
use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class FishReceptionPermissionService
{
    public const MODULE_SLUG = 'receptions';

    public function __construct(private SecurityAccessService $security)
    {
    }

    public function canAccess(User $user): bool
    {
        return $user->isActive() && $this->security->canAccessModule($user, self::MODULE_SLUG);
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canView(User $user, FishReception $reception): bool
    {
        return $this->canAccess($user) && !$reception->isDeleted();
    }

    public function canEdit(User $user, FishReception $reception): bool
    {
        return $this->canView($user, $reception) && !$reception->isLocked();
    }

    public function canTransition(User $user, FishReception $reception): bool
    {
        return $this->canView($user, $reception) && !$reception->isDeleted();
    }

    public function canDelete(User $user, FishReception $reception): bool
    {
        return $this->canView($user, $reception) && $this->security->isAdmin($user);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->security->isSuperAdmin($user);
    }
}
