<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryRequestRepository::class)]
#[ORM\Table(name: 'inventory_request')]
#[ORM\Index(name: 'idx_inventory_request_item', columns: ['item_id'])]
#[ORM\Index(name: 'idx_inventory_request_type', columns: ['request_type'])]
#[ORM\Index(name: 'idx_inventory_request_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inventory_request_from_site', columns: ['from_site_id'])]
#[ORM\Index(name: 'idx_inventory_request_from_location', columns: ['from_location_id'])]
#[ORM\Index(name: 'idx_inventory_request_to_site', columns: ['to_site_id'])]
#[ORM\Index(name: 'idx_inventory_request_to_location', columns: ['to_location_id'])]
#[ORM\Index(name: 'idx_inventory_request_movement', columns: ['movement_id'])]
#[ORM\Index(name: 'idx_inventory_request_result_item', columns: ['result_item_id'])]
#[ORM\Index(name: 'idx_inventory_request_validated_by', columns: ['validated_by_id'])]
#[ORM\Index(name: 'idx_inventory_request_canceled_by', columns: ['canceled_by_id'])]
#[ORM\Index(name: 'idx_inventory_request_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_request_updated_by', columns: ['updated_by_id'])]
class InventoryRequest
{
    use TimestampableUserTrait;

    public const TYPES = [
        'Transport' => 'transfer',
        'Inventaire' => 'inventory',
    ];

    public const STATUSES = [
        'En attente' => 'pending',
        'Validée' => 'validated',
        'Annulée' => 'canceled',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventoryItem $item = null;

    #[ORM\ManyToOne(targetEntity: InventoryMovement::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryMovement $movement = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryItem $resultItem = null;

    #[ORM\Column(name: 'request_type', length: 30)]
    private string $requestType = 'transfer';

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column]
    #[Assert\Positive]
    private int $requestedQuantity = 1;

    #[ORM\ManyToOne(targetEntity: InventorySite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventorySite $fromSite = null;

    #[ORM\ManyToOne(targetEntity: InventoryLocation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryLocation $fromLocation = null;

    #[ORM\ManyToOne(targetEntity: InventorySite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventorySite $toSite = null;

    #[ORM\ManyToOne(targetEntity: InventoryLocation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryLocation $toLocation = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $requestedLogisticsStatus = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $countedQuantity = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolutionNote = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $canceledBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $canceledAt = null;

    public function getId(): ?int { return $this->id; }
    public function getItem(): ?InventoryItem { return $this->item; }
    public function setItem(?InventoryItem $item): static { $this->item = $item; return $this; }
    public function getMovement(): ?InventoryMovement { return $this->movement; }
    public function setMovement(?InventoryMovement $movement): static { $this->movement = $movement; return $this; }
    public function getResultItem(): ?InventoryItem { return $this->resultItem; }
    public function setResultItem(?InventoryItem $resultItem): static { $this->resultItem = $resultItem; return $this; }
    public function getRequestType(): string { return $this->requestType; }
    public function setRequestType(string $requestType): static { $this->requestType = $requestType; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getRequestedQuantity(): int { return $this->requestedQuantity; }
    public function setRequestedQuantity(int $requestedQuantity): static { $this->requestedQuantity = max(1, $requestedQuantity); return $this; }
    public function getFromSite(): ?InventorySite { return $this->fromSite; }
    public function setFromSite(?InventorySite $fromSite): static { $this->fromSite = $fromSite; return $this; }
    public function getFromLocation(): ?InventoryLocation { return $this->fromLocation; }
    public function setFromLocation(?InventoryLocation $fromLocation): static { $this->fromLocation = $fromLocation; return $this; }
    public function getToSite(): ?InventorySite { return $this->toSite; }
    public function setToSite(?InventorySite $toSite): static { $this->toSite = $toSite; return $this; }
    public function getToLocation(): ?InventoryLocation { return $this->toLocation; }
    public function setToLocation(?InventoryLocation $toLocation): static { $this->toLocation = $toLocation; if ($toLocation?->getSite() !== null) { $this->toSite = $toLocation->getSite(); } return $this; }
    public function getRequestedLogisticsStatus(): ?string { return $this->requestedLogisticsStatus; }
    public function setRequestedLogisticsStatus(?string $requestedLogisticsStatus): static { $requestedLogisticsStatus = trim((string) $requestedLogisticsStatus); $this->requestedLogisticsStatus = $requestedLogisticsStatus !== '' ? $requestedLogisticsStatus : null; return $this; }
    public function getCountedQuantity(): ?int { return $this->countedQuantity; }
    public function setCountedQuantity(?int $countedQuantity): static { $this->countedQuantity = $countedQuantity !== null ? max(0, $countedQuantity) : null; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $reason = trim((string) $reason); $this->reason = $reason !== '' ? $reason : null; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function getResolutionNote(): ?string { return $this->resolutionNote; }
    public function setResolutionNote(?string $resolutionNote): static { $resolutionNote = trim((string) $resolutionNote); $this->resolutionNote = $resolutionNote !== '' ? $resolutionNote : null; return $this; }
    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }
    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }
    public function getCanceledBy(): ?User { return $this->canceledBy; }
    public function setCanceledBy(?User $canceledBy): static { $this->canceledBy = $canceledBy; return $this; }
    public function getCanceledAt(): ?\DateTimeImmutable { return $this->canceledAt; }
    public function setCanceledAt(?\DateTimeImmutable $canceledAt): static { $this->canceledAt = $canceledAt; return $this; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isTransfer(): bool { return $this->requestType === 'transfer'; }
    public function isInventory(): bool { return $this->requestType === 'inventory'; }
    public function markValidated(User $actor): static { $this->status = 'validated'; $this->validatedBy = $actor; $this->validatedAt = new \DateTimeImmutable(); return $this; }
    public function markCanceled(User $actor): static { $this->status = 'canceled'; $this->canceledBy = $actor; $this->canceledAt = new \DateTimeImmutable(); return $this; }
    public function getTypeLabel(): string { return array_flip(self::TYPES)[$this->requestType] ?? $this->requestType; }
    public function getStatusLabel(): string { return array_flip(self::STATUSES)[$this->status] ?? $this->status; }
    public function getRequestedLogisticsStatusLabel(): ?string { return $this->requestedLogisticsStatus !== null ? (array_flip(InventoryItem::LOGISTICS_STATUSES)[$this->requestedLogisticsStatus] ?? $this->requestedLogisticsStatus) : null; }
}
