<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\ConsumableStockItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsumableStockItemRepository::class)]
#[ORM\Table(name: 'consumable_stock_item')]
#[ORM\UniqueConstraint(name: 'uniq_consumable_stock_item_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_consumable_stock_item_name', columns: ['name'])]
#[ORM\Index(name: 'idx_consumable_stock_item_category', columns: ['category'])]
#[ORM\Index(name: 'idx_consumable_stock_item_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_consumable_stock_item_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_consumable_stock_item_updated_by', columns: ['updated_by_id'])]
class ConsumableStockItem
{
    use TimestampableUserTrait;

    public const UNIT_CHOICES = [
        'Piece' => 'piece',
        'Paire' => 'paire',
        'Boite' => 'boite',
        'Carton' => 'carton',
        'Paquet' => 'paquet',
        'Flacon' => 'flacon',
        'Litre' => 'litre',
        'Kg' => 'kg',
    ];

    public const CATEGORY_CHOICES = [
        'EPI' => 'EPI',
        'Hygiene' => 'Hygiene',
        'Nettoyage' => 'Nettoyage',
        'Emballage' => 'Emballage',
        'Bureau' => 'Bureau',
        'Maintenance' => 'Maintenance',
        'Autre' => 'Autre',
    ];

    public const LEVEL_OK = 'ok';
    public const LEVEL_LOW = 'low';
    public const LEVEL_OUT = 'out';

    public const LEVEL_LABELS = [
        self::LEVEL_OK => 'Stock OK',
        self::LEVEL_LOW => 'Stock bas',
        self::LEVEL_OUT => 'Rupture',
    ];

    public const LEVEL_BADGES = [
        self::LEVEL_OK => 'text-bg-success',
        self::LEVEL_LOW => 'text-bg-warning',
        self::LEVEL_OUT => 'text-bg-danger',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\Length(max: 80)]
    private ?string $reference = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $category = null;

    #[ORM\Column(length: 40, options: ['default' => 'piece'])]
    #[Assert\NotBlank]
    private string $unit = 'piece';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $quantity = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $minimumQuantity = '0.00';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $storageLocation = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $preferredSupplier = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $supplierPhone = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastInventoryAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, ConsumableStockMovement> */
    #[ORM\OneToMany(targetEntity: ConsumableStockMovement::class, mappedBy: 'item', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['movementDate' => 'DESC', 'id' => 'DESC'])]
    private Collection $movements;

    public function __construct()
    {
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = mb_strtoupper(trim($reference)); return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $category = trim((string) $category); $this->category = $category !== '' ? $category : null; return $this; }
    public function getCategoryLabel(): string { return $this->category ?: 'Sans categorie'; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $unit): static { $unit = trim($unit); $this->unit = $unit !== '' ? $unit : 'piece'; return $this; }
    public function getUnitLabel(): string { return array_flip(self::UNIT_CHOICES)[$this->unit] ?? $this->unit; }
    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(float|int|string $quantity): static { $this->quantity = $this->normalizeQuantity($quantity); return $this; }
    public function getQuantityValue(): float { return (float) $this->quantity; }
    public function getQuantityDisplay(): string { return $this->formatQuantity($this->quantity); }
    public function getMinimumQuantity(): string { return $this->minimumQuantity; }
    public function setMinimumQuantity(float|int|string $minimumQuantity): static { $this->minimumQuantity = $this->normalizeQuantity($minimumQuantity); return $this; }
    public function getMinimumQuantityValue(): float { return (float) $this->minimumQuantity; }
    public function getMinimumQuantityDisplay(): string { return $this->formatQuantity($this->minimumQuantity); }
    public function getStorageLocation(): ?string { return $this->storageLocation; }
    public function setStorageLocation(?string $storageLocation): static { $storageLocation = trim((string) $storageLocation); $this->storageLocation = $storageLocation !== '' ? $storageLocation : null; return $this; }
    public function getPreferredSupplier(): ?string { return $this->preferredSupplier; }
    public function setPreferredSupplier(?string $preferredSupplier): static { $preferredSupplier = trim((string) $preferredSupplier); $this->preferredSupplier = $preferredSupplier !== '' ? $preferredSupplier : null; return $this; }
    public function getSupplierPhone(): ?string { return $this->supplierPhone; }
    public function setSupplierPhone(?string $supplierPhone): static { $supplierPhone = trim((string) $supplierPhone); $this->supplierPhone = $supplierPhone !== '' ? $supplierPhone : null; return $this; }
    public function getLastInventoryAt(): ?\DateTimeImmutable { return $this->lastInventoryAt; }
    public function setLastInventoryAt(?\DateTimeImmutable $lastInventoryAt): static { $this->lastInventoryAt = $lastInventoryAt; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getStockLevel(): string
    {
        if ($this->getQuantityValue() <= 0.0) {
            return self::LEVEL_OUT;
        }

        if ($this->getMinimumQuantityValue() > 0.0 && $this->getQuantityValue() <= $this->getMinimumQuantityValue()) {
            return self::LEVEL_LOW;
        }

        return self::LEVEL_OK;
    }

    public function getStockLevelLabel(): string
    {
        return self::LEVEL_LABELS[$this->getStockLevel()] ?? $this->getStockLevel();
    }

    public function getStockLevelBadgeClass(): string
    {
        return self::LEVEL_BADGES[$this->getStockLevel()] ?? 'text-bg-light border';
    }

    public function needsRestock(): bool
    {
        return in_array($this->getStockLevel(), [self::LEVEL_LOW, self::LEVEL_OUT], true);
    }

    /** @return Collection<int, ConsumableStockMovement> */
    public function getMovements(): Collection { return $this->movements; }
    public function addMovement(ConsumableStockMovement $movement): static { if (!$this->movements->contains($movement)) { $this->movements->add($movement); $movement->setItem($this); } return $this; }
    public function removeMovement(ConsumableStockMovement $movement): static { if ($this->movements->removeElement($movement) && $movement->getItem() === $this) { $movement->setItem(null); } return $this; }

    private function normalizeQuantity(float|int|string $quantity): string
    {
        $value = (float) str_replace(',', '.', (string) $quantity);

        return number_format(max(0.0, $value), 2, '.', '');
    }

    private function formatQuantity(string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.') ?: '0';
    }
}
