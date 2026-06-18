<?php

namespace App\Security\Voter;

use App\Entity\InventoryAttachment;
use App\Entity\InventoryCampaign;
use App\Entity\InventoryCampaignLine;
use App\Entity\InventoryItem;
use App\Entity\User;
use App\Service\Inventory\InventoryAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class InventoryVoter extends Voter
{
    public const ACCESS = 'INVENTORY_ACCESS';
    public const ITEM_VIEW = 'INVENTORY_ITEM_VIEW';
    public const ITEM_CREATE = 'INVENTORY_ITEM_CREATE';
    public const ITEM_EDIT = 'INVENTORY_ITEM_EDIT';
    public const ITEM_DELETE = 'INVENTORY_ITEM_DELETE';
    public const ATTACHMENT_VIEW = 'INVENTORY_ATTACHMENT_VIEW';
    public const CAMPAIGN_MANAGE = 'INVENTORY_CAMPAIGN_MANAGE';

    public function __construct(private readonly InventoryAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::ACCESS, self::ITEM_CREATE => $subject === null,
            self::ITEM_VIEW, self::ITEM_EDIT, self::ITEM_DELETE => $subject instanceof InventoryItem,
            self::ATTACHMENT_VIEW => $subject instanceof InventoryAttachment,
            self::CAMPAIGN_MANAGE => $subject === null || $subject instanceof InventoryCampaign || $subject instanceof InventoryCampaignLine,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::ACCESS => $this->access->canAccess($user),
            self::ITEM_CREATE => $this->access->canCreate($user),
            self::ITEM_VIEW => $subject instanceof InventoryItem && $this->access->canViewItem($user, $subject),
            self::ITEM_EDIT => $subject instanceof InventoryItem && $this->access->canEditItem($user, $subject),
            self::ITEM_DELETE => $subject instanceof InventoryItem && $this->access->canDeleteItem($user, $subject),
            self::ATTACHMENT_VIEW => $subject instanceof InventoryAttachment && $this->access->canViewAttachment($user, $subject),
            self::CAMPAIGN_MANAGE => $this->canManageCampaign($user, $subject),
            default => false,
        };
    }

    private function canManageCampaign(User $user, mixed $subject): bool
    {
        if ($subject instanceof InventoryCampaignLine) {
            $subject = $subject->getCampaign();
        }

        return $this->access->canManageCampaign($user, $subject instanceof InventoryCampaign ? $subject : null);
    }
}
