<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryCartonStockLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryCartonStockLineRepository::class)]
#[ORM\Table(name: 'inventory_carton_stock_line')]
#[ORM\Index(name: 'idx_inventory_carton_line_stock', columns: ['stock_id'])]
#[ORM\Index(name: 'idx_inventory_carton_line_type', columns: ['line_type'])]
#[ORM\Index(name: 'idx_inventory_carton_line_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_inventory_carton_line_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_carton_line_updated_by', columns: ['updated_by_id'])]
class InventoryCartonStockLine
{
    use TimestampableUserTrait;

    public const LINE_TYPES = [
        'Ligne stock' => 'item',
        'Transport' => 'transport',
        'Total' => 'summary',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryCartonStock::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?InventoryCartonStock $stock = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $groupName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $reference = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $totalAmount = null;

    #[ORM\Column(name: 'line_type', length: 30)]
    private string $lineType = 'item';

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }
    public function getStock(): ?InventoryCartonStock { return $this->stock; }
    public function setStock(?InventoryCartonStock $stock): static { $this->stock = $stock; return $this; }
    public function getGroupName(): ?string { return $this->groupName; }
    public function setGroupName(?string $groupName): static { $groupName = trim((string) $groupName); $this->groupName = $groupName !== '' ? $groupName : null; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = trim($reference); return $this; }
    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(?int $quantity): static { $this->quantity = $quantity !== null ? max(0, $quantity) : null; return $this; }
    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(null|float|int|string $unitPrice): static { $this->unitPrice = $this->normalizeDecimal($unitPrice); return $this; }
    public function getTotalAmount(): ?string { return $this->totalAmount; }
    public function setTotalAmount(null|float|int|string $totalAmount): static { $this->totalAmount = $this->normalizeDecimal($totalAmount); return $this; }
    public function getLineType(): string { return $this->lineType; }
    public function setLineType(string $lineType): static { $this->lineType = in_array($lineType, self::LINE_TYPES, true) ? $lineType : 'item'; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = max(0, $position); return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function isSummary(): bool { return $this->lineType === 'summary'; }
    public function isTransport(): bool { return $this->lineType === 'transport'; }
    public function getLineTypeLabel(): string { return array_flip(self::LINE_TYPES)[$this->lineType] ?? $this->lineType; }
    public function getUnitPriceLabel(): ?string { return $this->formatDecimal($this->unitPrice); }
    public function getTotalAmountLabel(): ?string { return $this->formatDecimal($this->totalAmount); }

    private function normalizeDecimal(null|float|int|string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace(["\u{00A0}", ' '], '', (string) $value);
        $value = str_replace(',', '.', $value);

        return number_format((float) $value, 3, '.', '');
    }

    private function formatDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = number_format((float) $value, 3, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
