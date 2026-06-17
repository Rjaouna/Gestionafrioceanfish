<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\ModuleAccessService;
use App\Service\SecurityAccessService;
use App\Service\Trash\TrashService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly ModuleAccessService $moduleAccessService,
        private readonly TrashService $trashService,
        private readonly SecurityAccessService $securityAccess,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('accessible_modules', $this->accessibleModules(...)),
            new TwigFunction('trash_deleted_count', $this->trashDeletedCount(...)),
        ];
    }

    public function accessibleModules(?User $user): array
    {
        return $user ? $this->moduleAccessService->getAccessibleModules($user) : [];
    }

    public function trashDeletedCount(?User $user): int
    {
        if (!$user instanceof User || !$this->securityAccess->isSuperAdmin($user)) {
            return 0;
        }

        return $this->trashService->countDeletedItems();
    }
}
