<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryMovementRepository::class)]
#[ORM\Table(name: 'inventory_movement')]
#[ORM\Index(name: 'idx_inventory_movement_item', columns: ['item_id'])]
#[ORM\Index(name: 'idx_inventory_movement_type', columns: ['movement_type'])]
#[ORM\Index(name: 'idx_inventory_movement_date', columns: ['movement_date'])]
#[ORM\Index(name: 'idx_inventory_movement_from_site', columns: ['from_site_id'])]
#[ORM\Index(name: 'idx_inventory_movement_from_location', columns: ['from_location_id'])]
#[ORM\Index(name: 'idx_inventory_movement_to_site', columns: ['to_site_id'])]
#[ORM\Index(name: 'idx_inventory_movement_to_location', columns: ['to_location_id'])]
#[ORM\Index(name: 'idx_inventory_movement_responsible', columns: ['responsible_user_id'])]
#[ORM\Index(name: 'idx_inventory_movement_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_movement_updated_by', columns: ['updated_by_id'])]
class InventoryMovement
{
    use TimestampableUserTrait;

    public const TYPES = [
        'Entree' => 'entry',
        'Transfert' => 'transfer',
        'Affectation' => 'assignment',
        'Retour' => 'return',
        'Maintenance' => 'maintenance',
        'Ajustement' => 'adjustment',
        'Sortie du parc' => 'retirement',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventoryItem $item = null;

    #[ORM\Column(length: 30)]
    private string $movementType = 'transfer';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $quantity = 1;

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsibleUser = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $movementDate;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->movementDate = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getItem(): ?InventoryItem { return $this->item; }
    public function setItem(?InventoryItem $item): static { $this->item = $item; return $this; }
    public function getMovementType(): string { return $this->movementType; }
    public function setMovementType(string $movementType): static { $this->movementType = $movementType; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(0, $quantity); return $this; }
    public function getFromSite(): ?InventorySite { return $this->fromSite; }
    public function setFromSite(?InventorySite $fromSite): static { $this->fromSite = $fromSite; return $this; }
    public function getFromLocation(): ?InventoryLocation { return $this->fromLocation; }
    public function setFromLocation(?InventoryLocation $fromLocation): static { $this->fromLocation = $fromLocation; return $this; }
    public function getToSite(): ?InventorySite { return $this->toSite; }
    public function setToSite(?InventorySite $toSite): static { $this->toSite = $toSite; return $this; }
    public function getToLocation(): ?InventoryLocation { return $this->toLocation; }
    public function setToLocation(?InventoryLocation $toLocation): static { $this->toLocation = $toLocation; if ($toLocation?->getSite() !== null) { $this->toSite = $toLocation->getSite(); } return $this; }
    public function getResponsibleUser(): ?User { return $this->responsibleUser; }
    public function setResponsibleUser(?User $responsibleUser): static { $this->responsibleUser = $responsibleUser; return $this; }
    public function getMovementDate(): \DateTimeImmutable { return $this->movementDate; }
    public function setMovementDate(\DateTimeImmutable $movementDate): static { $this->movementDate = $movementDate; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $reason = trim((string) $reason); $this->reason = $reason !== '' ? $reason : null; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function getTypeLabel(): string { return array_flip(self::TYPES)[$this->movementType] ?? $this->movementType; }
}
