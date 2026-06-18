<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryCampaignLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryCampaignLineRepository::class)]
#[ORM\Table(name: 'inventory_campaign_line')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_campaign_item', fields: ['campaign', 'item'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_campaign', columns: ['campaign_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_item', columns: ['item_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_status', columns: ['check_status'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_checked_by', columns: ['checked_by_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_line_updated_by', columns: ['updated_by_id'])]
class InventoryCampaignLine
{
    use TimestampableUserTrait;

    public const CHECK_STATUSES = [
        'À vérifier' => 'pending',
        'Conforme' => 'ok',
        'Écart' => 'discrepancy',
        'Introuvable' => 'missing',
        'Nouvel emplacement' => 'relocated',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryCampaign::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventoryCampaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventoryItem $item = null;

    #[ORM\Column(length: 30)]
    private string $checkStatus = 'pending';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $theoreticalQuantity = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $countedQuantity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $theoreticalLocation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $countedLocation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $checkedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getCampaign(): ?InventoryCampaign { return $this->campaign; }
    public function setCampaign(?InventoryCampaign $campaign): static { $this->campaign = $campaign; return $this; }
    public function getItem(): ?InventoryItem { return $this->item; }
    public function setItem(?InventoryItem $item): static { $this->item = $item; return $this; }
    public function getCheckStatus(): string { return $this->checkStatus; }
    public function setCheckStatus(string $checkStatus): static { $this->checkStatus = $checkStatus; return $this; }
    public function getTheoreticalQuantity(): int { return $this->theoreticalQuantity; }
    public function setTheoreticalQuantity(int $theoreticalQuantity): static { $this->theoreticalQuantity = max(0, $theoreticalQuantity); return $this; }
    public function getCountedQuantity(): ?int { return $this->countedQuantity; }
    public function setCountedQuantity(?int $countedQuantity): static { $this->countedQuantity = $countedQuantity === null ? null : max(0, $countedQuantity); return $this; }
    public function getTheoreticalLocation(): ?string { return $this->theoreticalLocation; }
    public function setTheoreticalLocation(?string $theoreticalLocation): static { $theoreticalLocation = trim((string) $theoreticalLocation); $this->theoreticalLocation = $theoreticalLocation !== '' ? $theoreticalLocation : null; return $this; }
    public function getCountedLocation(): ?string { return $this->countedLocation; }
    public function setCountedLocation(?string $countedLocation): static { $countedLocation = trim((string) $countedLocation); $this->countedLocation = $countedLocation !== '' ? $countedLocation : null; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $comment = trim((string) $comment); $this->comment = $comment !== '' ? $comment : null; return $this; }
    public function getCheckedBy(): ?User { return $this->checkedBy; }
    public function setCheckedBy(?User $checkedBy): static { $this->checkedBy = $checkedBy; return $this; }
    public function getCheckedAt(): ?\DateTimeImmutable { return $this->checkedAt; }
    public function setCheckedAt(?\DateTimeImmutable $checkedAt): static { $this->checkedAt = $checkedAt; return $this; }
    public function hasDiscrepancy(): bool { return $this->countedQuantity !== null && ($this->countedQuantity !== $this->theoreticalQuantity || trim((string) $this->countedLocation) !== trim((string) $this->theoreticalLocation)); }
    public function getCheckStatusLabel(): string { return array_flip(self::CHECK_STATUSES)[$this->checkStatus] ?? $this->checkStatus; }
}
