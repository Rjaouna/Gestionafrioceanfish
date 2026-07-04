<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\CoutRevientChargeLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoutRevientChargeLineRepository::class)]
#[ORM\Table(name: 'cout_revient_charge_line')]
#[ORM\Index(name: 'idx_cout_charge_line_cout_revient', columns: ['cout_revient_id'])]
#[ORM\Index(name: 'idx_cout_charge_line_config', columns: ['charge_config_id'])]
#[ORM\Index(name: 'idx_cout_charge_line_category', columns: ['category'])]
#[ORM\Index(name: 'idx_cout_charge_line_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_cout_charge_line_updated_by', columns: ['updated_by_id'])]
class CoutRevientChargeLine
{
    use TimestampableUserTrait;

    private const DAYS_PER_MONTH = 30.0;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CoutRevient::class, inversedBy: 'chargeLines')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CoutRevient $coutRevient = null;

    #[ORM\ManyToOne(targetEntity: CoutRevientChargeConfig::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CoutRevientChargeConfig $chargeConfig = null;

    #[ORM\Column(length: 140)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 140)]
    private string $name = '';

    #[ORM\Column(length: 40, options: ['default' => CoutRevientChargeConfig::CATEGORY_OTHER])]
    private string $category = CoutRevientChargeConfig::CATEGORY_OTHER;

    #[ORM\Column(length: 30, options: ['default' => CoutRevientChargeConfig::UNIT_DIRECT])]
    private string $calculationUnit = CoutRevientChargeConfig::UNIT_DIRECT;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, options: ['default' => '0.0000'])]
    #[Assert\PositiveOrZero]
    private string $unitCost = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '1.000'])]
    #[Assert\PositiveOrZero]
    private string $quantity = '1.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoutRevient(): ?CoutRevient
    {
        return $this->coutRevient;
    }

    public function setCoutRevient(?CoutRevient $coutRevient): static
    {
        $this->coutRevient = $coutRevient;

        return $this;
    }

    public function getChargeConfig(): ?CoutRevientChargeConfig
    {
        return $this->chargeConfig;
    }

    public function setChargeConfig(?CoutRevientChargeConfig $chargeConfig): static
    {
        $this->chargeConfig = $chargeConfig;

        return $this;
    }

    public function applyConfig(CoutRevientChargeConfig $config): static
    {
        return $this
            ->setChargeConfig($config)
            ->setName((string) $config->getName())
            ->setCategory($config->getCategory())
            ->setCalculationUnit($config->getCalculationUnit())
            ->setUnitCost($config->getUnitCost());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $category = (string) $category;
        $this->category = isset(CoutRevientChargeConfig::CATEGORY_LABELS[$category])
            ? $category
            : CoutRevientChargeConfig::CATEGORY_OTHER;

        return $this;
    }

    public function getCategoryLabel(): string
    {
        return CoutRevientChargeConfig::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getCalculationUnit(): string
    {
        return $this->calculationUnit;
    }

    public function setCalculationUnit(?string $calculationUnit): static
    {
        $calculationUnit = (string) $calculationUnit;
        $this->calculationUnit = isset(CoutRevientChargeConfig::UNIT_LABELS[$calculationUnit])
            ? $calculationUnit
            : CoutRevientChargeConfig::UNIT_DIRECT;

        return $this;
    }

    public function getCalculationUnitLabel(): string
    {
        return CoutRevientChargeConfig::UNIT_LABELS[$this->calculationUnit] ?? $this->calculationUnit;
    }

    public function getCalculationUnitShortLabel(): string
    {
        return CoutRevientChargeConfig::UNIT_SHORT_LABELS[$this->calculationUnit] ?? $this->calculationUnit;
    }

    public function getQuantityLabel(): string
    {
        return match ($this->calculationUnit) {
            CoutRevientChargeConfig::UNIT_MONTH => 'Jours utilises',
            CoutRevientChargeConfig::UNIT_DAY => 'Jours',
            CoutRevientChargeConfig::UNIT_HOUR => 'Heures',
            CoutRevientChargeConfig::UNIT_KG => 'Kg',
            CoutRevientChargeConfig::UNIT_LOT => 'Nombre de lots',
            default => 'Quantite',
        };
    }

    public function getAppliedUnitLabel(): string
    {
        return $this->calculationUnit === CoutRevientChargeConfig::UNIT_MONTH ? 'jour' : $this->getCalculationUnitShortLabel();
    }

    public function getAppliedUnitCost(): float
    {
        $unitCost = (float) $this->unitCost;

        return $this->calculationUnit === CoutRevientChargeConfig::UNIT_MONTH ? $unitCost / self::DAYS_PER_MONTH : $unitCost;
    }

    public function getUnitCost(): string
    {
        return $this->unitCost;
    }

    public function setUnitCost(int|float|string|null $unitCost): static
    {
        $this->unitCost = $this->decimal($unitCost, 4);

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(int|float|string|null $quantity): static
    {
        $this->quantity = $this->decimal($quantity, 3);

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int|float|string|null $totalAmount): static
    {
        $this->totalAmount = $this->decimal($totalAmount);

        return $this;
    }

    public function recalculate(): static
    {
        return $this->setTotalAmount($this->getAppliedUnitCost() * (float) $this->quantity);
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = trim((string) $note);
        $this->note = $note !== '' ? $note : null;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int|string|null $sortOrder): static
    {
        $this->sortOrder = max(0, (int) $sortOrder);

        return $this;
    }

    private function decimal(int|float|string|null $value, int $scale = 2): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format(max(0, (float) $normalized), $scale, '.', '');
    }
}
