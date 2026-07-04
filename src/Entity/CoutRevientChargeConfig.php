<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\CoutRevientChargeConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoutRevientChargeConfigRepository::class)]
#[ORM\Table(name: 'cout_revient_charge_config')]
#[ORM\Index(name: 'idx_cout_charge_config_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_cout_charge_config_category', columns: ['category'])]
#[ORM\Index(name: 'idx_cout_charge_config_unit', columns: ['calculation_unit'])]
#[ORM\Index(name: 'idx_cout_charge_config_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_cout_charge_config_updated_by', columns: ['updated_by_id'])]
class CoutRevientChargeConfig
{
    use TimestampableUserTrait;

    public const CATEGORY_ENERGY = 'energie';
    public const CATEGORY_WATER = 'eau';
    public const CATEGORY_COLD = 'froid';
    public const CATEGORY_STORAGE = 'stockage';
    public const CATEGORY_PRODUCTION = 'production';
    public const CATEGORY_CLEANING = 'nettoyage';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_LOGISTICS = 'logistique';
    public const CATEGORY_OTHER = 'autre';

    public const CATEGORY_LABELS = [
        self::CATEGORY_ENERGY => 'Energie',
        self::CATEGORY_WATER => 'Eau',
        self::CATEGORY_COLD => 'Froid / tunnel',
        self::CATEGORY_STORAGE => 'Stockage',
        self::CATEGORY_PRODUCTION => 'Production',
        self::CATEGORY_CLEANING => 'Nettoyage',
        self::CATEGORY_MAINTENANCE => 'Maintenance',
        self::CATEGORY_LOGISTICS => 'Logistique',
        self::CATEGORY_OTHER => 'Autre',
    ];

    public const UNIT_HOUR = 'heure';
    public const UNIT_DAY = 'jour';
    public const UNIT_MONTH = 'mois';
    public const UNIT_KG = 'kg';
    public const UNIT_LOT = 'lot';
    public const UNIT_DIRECT = 'montant_direct';

    public const UNIT_LABELS = [
        self::UNIT_HOUR => 'Par heure',
        self::UNIT_DAY => 'Par jour',
        self::UNIT_MONTH => 'Par mois',
        self::UNIT_KG => 'Par kg',
        self::UNIT_LOT => 'Forfait lot',
        self::UNIT_DIRECT => 'Montant direct',
    ];

    public const UNIT_SHORT_LABELS = [
        self::UNIT_HOUR => 'heure',
        self::UNIT_DAY => 'jour',
        self::UNIT_MONTH => 'mois',
        self::UNIT_KG => 'kg',
        self::UNIT_LOT => 'lot',
        self::UNIT_DIRECT => 'direct',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 140)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 140)]
    private ?string $name = null;

    #[ORM\Column(length: 40, options: ['default' => self::CATEGORY_OTHER])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::CATEGORY_ENERGY,
        self::CATEGORY_WATER,
        self::CATEGORY_COLD,
        self::CATEGORY_STORAGE,
        self::CATEGORY_PRODUCTION,
        self::CATEGORY_CLEANING,
        self::CATEGORY_MAINTENANCE,
        self::CATEGORY_LOGISTICS,
        self::CATEGORY_OTHER,
    ])]
    private string $category = self::CATEGORY_OTHER;

    #[ORM\Column(length: 30, options: ['default' => self::UNIT_DIRECT])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::UNIT_HOUR,
        self::UNIT_DAY,
        self::UNIT_MONTH,
        self::UNIT_KG,
        self::UNIT_LOT,
        self::UNIT_DIRECT,
    ])]
    private string $calculationUnit = self::UNIT_DIRECT;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, options: ['default' => '0.0000'])]
    #[Assert\PositiveOrZero]
    private string $unitCost = '0.0000';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        if (!isset(self::CATEGORY_LABELS[$category])) {
            throw new \InvalidArgumentException('Categorie de charge invalide.');
        }

        $this->category = $category;

        return $this;
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getCalculationUnit(): string
    {
        return $this->calculationUnit;
    }

    public function setCalculationUnit(string $calculationUnit): static
    {
        if (!isset(self::UNIT_LABELS[$calculationUnit])) {
            throw new \InvalidArgumentException('Unite de calcul invalide.');
        }

        $this->calculationUnit = $calculationUnit;

        return $this;
    }

    public function getCalculationUnitLabel(): string
    {
        return self::UNIT_LABELS[$this->calculationUnit] ?? $this->calculationUnit;
    }

    public function getCalculationUnitShortLabel(): string
    {
        return self::UNIT_SHORT_LABELS[$this->calculationUnit] ?? $this->calculationUnit;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = trim((string) $description);
        $this->description = $description !== '' ? $description : null;

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

        return number_format((float) $normalized, $scale, '.', '');
    }
}
