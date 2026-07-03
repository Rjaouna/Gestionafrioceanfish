<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\ConsumableStockMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsumableStockMovementRepository::class)]
#[ORM\Table(name: 'consumable_stock_movement')]
#[ORM\Index(name: 'idx_consumable_stock_movement_item', columns: ['item_id'])]
#[ORM\Index(name: 'idx_consumable_stock_movement_type', columns: ['movement_type'])]
#[ORM\Index(name: 'idx_consumable_stock_movement_date', columns: ['movement_date'])]
#[ORM\Index(name: 'idx_consumable_stock_movement_performed_by', columns: ['performed_by_id'])]
#[ORM\Index(name: 'idx_consumable_stock_movement_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_consumable_stock_movement_updated_by', columns: ['updated_by_id'])]
class ConsumableStockMovement
{
    use TimestampableUserTrait;

    public const TYPE_ENTRY = 'entry';
    public const TYPE_EXIT = 'exit';
    public const TYPE_INVENTORY = 'inventory';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_LABELS = [
        self::TYPE_ENTRY => 'Entree',
        self::TYPE_EXIT => 'Sortie',
        self::TYPE_INVENTORY => 'Inventaire',
        self::TYPE_ADJUSTMENT => 'Ajustement',
    ];

    public const TYPE_BADGES = [
        self::TYPE_ENTRY => 'text-bg-success',
        self::TYPE_EXIT => 'text-bg-warning',
        self::TYPE_INVENTORY => 'text-bg-primary',
        self::TYPE_ADJUSTMENT => 'text-bg-secondary',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ConsumableStockItem::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConsumableStockItem $item = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    private string $movementType = self::TYPE_ENTRY;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $quantity = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $previousQuantity = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $newQuantity = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $unitCost = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $movementDate;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $supplier = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $recipient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    public function __construct()
    {
        $this->movementDate = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getItem(): ?ConsumableStockItem { return $this->item; }
    public function setItem(?ConsumableStockItem $item): static { $this->item = $item; return $this; }
    public function getMovementType(): string { return $this->movementType; }
    public function setMovementType(string $movementType): static { $this->movementType = isset(self::TYPE_LABELS[$movementType]) ? $movementType : self::TYPE_ADJUSTMENT; return $this; }
    public function getMovementTypeLabel(): string { return self::TYPE_LABELS[$this->movementType] ?? $this->movementType; }
    public function getMovementTypeBadgeClass(): string { return self::TYPE_BADGES[$this->movementType] ?? 'text-bg-light border'; }
    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(float|int|string $quantity): static { $this->quantity = $this->normalizeSignedQuantity($quantity); return $this; }
    public function getQuantityValue(): float { return (float) $this->quantity; }
    public function getQuantityDisplay(): string { return $this->formatQuantity($this->quantity); }
    public function getPreviousQuantity(): string { return $this->previousQuantity; }
    public function setPreviousQuantity(float|int|string $previousQuantity): static { $this->previousQuantity = $this->normalizeSignedQuantity($previousQuantity); return $this; }
    public function getPreviousQuantityDisplay(): string { return $this->formatQuantity($this->previousQuantity); }
    public function getNewQuantity(): string { return $this->newQuantity; }
    public function setNewQuantity(float|int|string $newQuantity): static { $this->newQuantity = $this->normalizeSignedQuantity($newQuantity); return $this; }
    public function getNewQuantityDisplay(): string { return $this->formatQuantity($this->newQuantity); }
    public function getUnitCost(): ?string { return $this->unitCost; }
    public function setUnitCost(null|float|int|string $unitCost): static { $this->unitCost = $unitCost !== null && $unitCost !== '' ? $this->normalizeUnsignedQuantity($unitCost) : null; return $this; }
    public function getMovementDate(): \DateTimeImmutable { return $this->movementDate; }
    public function setMovementDate(\DateTimeImmutable $movementDate): static { $this->movementDate = $movementDate; return $this; }
    public function getSupplier(): ?string { return $this->supplier; }
    public function setSupplier(?string $supplier): static { $supplier = trim((string) $supplier); $this->supplier = $supplier !== '' ? $supplier : null; return $this; }
    public function getRecipient(): ?string { return $this->recipient; }
    public function setRecipient(?string $recipient): static { $recipient = trim((string) $recipient); $this->recipient = $recipient !== '' ? $recipient : null; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $reason = trim((string) $reason); $this->reason = $reason !== '' ? $reason : null; return $this; }
    public function getPerformedBy(): ?User { return $this->performedBy; }
    public function setPerformedBy(?User $performedBy): static { $this->performedBy = $performedBy; return $this; }

    private function normalizeSignedQuantity(float|int|string $quantity): string
    {
        $value = (float) str_replace(',', '.', (string) $quantity);

        return number_format($value, 2, '.', '');
    }

    private function normalizeUnsignedQuantity(float|int|string $quantity): string
    {
        $value = (float) str_replace(',', '.', (string) $quantity);

        return number_format(max(0.0, $value), 2, '.', '');
    }

    private function formatQuantity(string $quantity): string
    {
        $value = rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');

        return $value === '-0' || $value === '' ? '0' : $value;
    }
}
