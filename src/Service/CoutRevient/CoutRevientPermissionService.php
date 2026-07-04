<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevient;
use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class CoutRevientPermissionService
{
    public const MODULE_SLUG = 'cout-revient';

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

    public function canView(User $user, CoutRevient $coutRevient): bool
    {
        return $this->canAccess($user) && !$coutRevient->isDeleted();
    }

    public function canEdit(User $user, CoutRevient $coutRevient): bool
    {
        return $this->canView($user, $coutRevient) && $coutRevient->getStatut() !== CoutRevient::STATUS_ARCHIVED;
    }

    public function canValidate(User $user, CoutRevient $coutRevient): bool
    {
        return $this->canEdit($user, $coutRevient) && $coutRevient->getStatut() !== CoutRevient::STATUS_VALIDATED;
    }

    public function canDuplicate(User $user, CoutRevient $coutRevient): bool
    {
        return $this->canView($user, $coutRevient);
    }

    public function canExport(User $user, ?CoutRevient $coutRevient = null): bool
    {
        return $coutRevient instanceof CoutRevient ? $this->canView($user, $coutRevient) : $this->canAccess($user);
    }

    public function canDelete(User $user, CoutRevient $coutRevient): bool
    {
        return $this->canView($user, $coutRevient) && $this->security->isAdmin($user);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->security->isSuperAdmin($user);
    }
}
