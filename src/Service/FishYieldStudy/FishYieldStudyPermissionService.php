<?php

namespace App\Service\FishYieldStudy;

use App\Entity\FishYieldStudy;
use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class FishYieldStudyPermissionService
{
    public const MODULE_SLUG = 'cout-revient';

    public function __construct(private SecurityAccessService $security)
    {
    }

    public function canAccess(User $user): bool
    {
        return $this->security->canAccessModule($user, self::MODULE_SLUG);
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canView(User $user, FishYieldStudy $study): bool
    {
        return $this->canAccess($user) && !$study->isDeleted();
    }

    public function canEdit(User $user, FishYieldStudy $study): bool
    {
        return $this->canView($user, $study);
    }

    public function canDelete(User $user, FishYieldStudy $study): bool
    {
        return $this->canView($user, $study) && $this->security->isAdmin($user);
    }

    public function canPrint(User $user, ?FishYieldStudy $study = null): bool
    {
        return $this->canAccess($user) && ($study === null || !$study->isDeleted());
    }
}
