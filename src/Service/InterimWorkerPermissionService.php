<?php

namespace App\Service;

use App\Entity\InterimWorker;
use App\Entity\InterimWorkerDocument;
use App\Entity\User;

final readonly class InterimWorkerPermissionService
{
    public const MODULE_SLUG = 'interimaires';

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

    public function canView(User $user, InterimWorker $worker): bool
    {
        return $this->canAccess($user) && !$worker->isDeleted();
    }

    public function canEdit(User $user, InterimWorker $worker): bool
    {
        return $this->canView($user, $worker);
    }

    public function canDelete(User $user, InterimWorker $worker): bool
    {
        return $this->canView($user, $worker) && $this->security->isAdmin($user);
    }

    public function canDownloadDocument(User $user, InterimWorkerDocument $document): bool
    {
        $worker = $document->getWorker();

        return $worker instanceof InterimWorker && $this->canView($user, $worker);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->security->isSuperAdmin($user);
    }
}
