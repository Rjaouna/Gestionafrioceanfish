<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\MaintenanceShareRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceShareRepository::class)]
#[ORM\Table(name: 'maintenance_share')]
#[ORM\UniqueConstraint(name: 'uniq_maintenance_share_user', fields: ['itemType', 'itemId', 'user'])]
#[ORM\Index(name: 'idx_maintenance_share_item', columns: ['item_type', 'item_id'])]
#[ORM\Index(name: 'idx_maintenance_share_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_maintenance_share_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_maintenance_share_updated_by', columns: ['updated_by_id'])]
class MaintenanceShare
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $itemType = '';

    #[ORM\Column]
    private int $itemId = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $canView = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): static
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function canView(): bool
    {
        return $this->canView;
    }

    public function setCanView(bool $canView): static
    {
        $this->canView = $canView;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
