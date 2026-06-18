<?php

namespace App\Service\Inventory;

use App\Entity\InventoryAttachment;
use App\Entity\InventoryCampaign;
use App\Entity\InventoryItem;
use App\Entity\User;
use App\Service\SecurityAccessService;

final readonly class InventoryAccessService
{
    public const MODULE_SLUG = 'inventory';

    public function __construct(private SecurityAccessService $securityAccess)
    {
    }

    public function canAccess(User $user): bool
    {
        return $user->isActive() && $this->securityAccess->canAccessModule($user, self::MODULE_SLUG);
    }

    public function isAdmin(User $user): bool
    {
        return $this->securityAccess->isAdmin($user);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->securityAccess->isSuperAdmin($user);
    }

    public function canViewAll(User $user): bool
    {
        return $this->canAccess($user) && $this->isAdmin($user);
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canViewItem(User $user, InventoryItem $item): bool
    {
        if (!$this->canAccess($user) || $item->isDeleted()) {
            return false;
        }

        return $this->canViewAll($user)
            || $item->getResponsibleUser()?->getId() === $user->getId()
            || $item->getCreatedBy()?->getId() === $user->getId();
    }

    public function canEditItem(User $user, InventoryItem $item): bool
    {
        return $this->canViewItem($user, $item) && $item->isActive();
    }

    public function canDeleteItem(User $user, InventoryItem $item): bool
    {
        return $this->canViewItem($user, $item) && $this->isAdmin($user);
    }

    public function canViewAttachment(User $user, InventoryAttachment $attachment): bool
    {
        $item = $attachment->getItem();

        return $item instanceof InventoryItem && $this->canViewItem($user, $item);
    }

    public function canManageCampaign(User $user, ?InventoryCampaign $campaign = null): bool
    {
        if (!$this->canAccess($user)) {
            return false;
        }

        if ($campaign === null || $this->canViewAll($user)) {
            return true;
        }

        return $campaign->getResponsibleUser()?->getId() === $user->getId()
            || $campaign->getCreatedBy()?->getId() === $user->getId();
    }
}
