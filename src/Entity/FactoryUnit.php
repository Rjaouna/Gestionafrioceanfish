<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\FactoryUnitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FactoryUnitRepository::class)]
#[ORM\Table(name: 'factory_unit')]
#[ORM\UniqueConstraint(name: 'uniq_factory_unit_code', fields: ['code'])]
#[ORM\Index(name: 'idx_factory_unit_type', columns: ['type'])]
#[ORM\Index(name: 'idx_factory_unit_status', columns: ['status'])]
#[ORM\Index(name: 'idx_factory_unit_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_factory_unit_saturated', columns: ['is_saturated'])]
#[ORM\Index(name: 'idx_factory_unit_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_factory_unit_updated_by', columns: ['updated_by_id'])]
class FactoryUnit
{
    use TimestampableUserTrait;

    public const TYPE_TUNNEL = 'tunnel';
    public const TYPE_NEGATIVE_ROOM = 'chambre_negative';
    public const TYPE_POSITIVE_ROOM = 'chambre_positive';
    public const TYPE_PRODUCTION_ZONE = 'zone_production';
    public const TYPE_PACKAGING_ZONE = 'zone_emballage';
    public const TYPE_STORAGE_ZONE = 'zone_stockage';
    public const TYPE_OTHER = 'autre';

    public const TYPE_LABELS = [
        self::TYPE_TUNNEL => 'Tunnel de congelation',
        self::TYPE_NEGATIVE_ROOM => 'Chambre negative',
        self::TYPE_POSITIVE_ROOM => 'Chambre positive',
        self::TYPE_PRODUCTION_ZONE => 'Zone production',
        self::TYPE_PACKAGING_ZONE => 'Zone emballage',
        self::TYPE_STORAGE_ZONE => 'Zone stockage',
        self::TYPE_OTHER => 'Autre',
    ];

    public const STATUS_OPERATIONAL = 'operationnel';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_STOPPED = 'arrete';

    public const STATUS_LABELS = [
        self::STATUS_OPERATIONAL => 'Operationnel',
        self::STATUS_MAINTENANCE => 'Maintenance',
        self::STATUS_STOPPED => 'Arrete',
    ];

    public const STATUS_BADGES = [
        self::STATUS_OPERATIONAL => 'text-bg-success',
        self::STATUS_MAINTENANCE => 'text-bg-warning',
        self::STATUS_STOPPED => 'text-bg-secondary',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 60)]
    #[Assert\Length(max: 60)]
    private ?string $code = null;

    #[ORM\Column(length: 40, options: ['default' => self::TYPE_OTHER])]
    #[Assert\Choice(choices: [
        self::TYPE_TUNNEL,
        self::TYPE_NEGATIVE_ROOM,
        self::TYPE_POSITIVE_ROOM,
        self::TYPE_PRODUCTION_ZONE,
        self::TYPE_PACKAGING_ZONE,
        self::TYPE_STORAGE_ZONE,
        self::TYPE_OTHER,
    ])]
    private string $type = self::TYPE_OTHER;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_OPERATIONAL])]
    #[Assert\Choice(choices: [
        self::STATUS_OPERATIONAL,
        self::STATUS_MAINTENANCE,
        self::STATUS_STOPPED,
    ])]
    private string $status = self::STATUS_OPERATIONAL;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSaturated = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $capacityKg = '0.000';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $capacityPallets = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $capacityBoxes = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $lengthMeters = '0.00';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $widthMeters = '0.00';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $heightMeters = '0.00';

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $floorLevel = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $locationLabel = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $targetTemperature = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $minTemperature = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $maxTemperature = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1200)]
    private ?string $description = null;

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $this->nullableString($name) ?? ''; return $this; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = strtoupper((string) ($this->nullableString($code) ?? '')); return $this; }

    public function getType(): string { return $this->type; }
    public function setType(?string $type): static
    {
        $this->type = isset(self::TYPE_LABELS[(string) $type]) ? (string) $type : self::TYPE_OTHER;

        return $this;
    }

    public function getTypeLabel(): string { return self::TYPE_LABELS[$this->type] ?? $this->type; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(?string $status): static
    {
        $this->status = isset(self::STATUS_LABELS[(string) $status]) ? (string) $status : self::STATUS_OPERATIONAL;

        return $this;
    }

    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getStatusBadgeClass(): string { return self::STATUS_BADGES[$this->status] ?? 'text-bg-secondary'; }

    public function isSaturated(): bool { return $this->isSaturated; }
    public function setIsSaturated(bool $isSaturated): static { $this->isSaturated = $isSaturated; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCapacityKg(): string { return $this->capacityKg; }
    public function setCapacityKg(int|float|string|null $capacityKg): static { $this->capacityKg = $this->decimal($capacityKg, 3); return $this; }

    public function getCapacityPallets(): int { return $this->capacityPallets; }
    public function setCapacityPallets(int|string|null $capacityPallets): static { $this->capacityPallets = max(0, (int) $capacityPallets); return $this; }

    public function getCapacityBoxes(): int { return $this->capacityBoxes; }
    public function setCapacityBoxes(int|string|null $capacityBoxes): static { $this->capacityBoxes = max(0, (int) $capacityBoxes); return $this; }

    public function getLengthMeters(): string { return $this->lengthMeters; }
    public function setLengthMeters(int|float|string|null $lengthMeters): static { $this->lengthMeters = $this->decimal($lengthMeters); return $this; }

    public function getWidthMeters(): string { return $this->widthMeters; }
    public function setWidthMeters(int|float|string|null $widthMeters): static { $this->widthMeters = $this->decimal($widthMeters); return $this; }

    public function getHeightMeters(): string { return $this->heightMeters; }
    public function setHeightMeters(int|float|string|null $heightMeters): static { $this->heightMeters = $this->decimal($heightMeters); return $this; }

    public function getSurfaceM2(): float
    {
        return (float) $this->lengthMeters * (float) $this->widthMeters;
    }

    public function getVolumeM3(): float
    {
        return $this->getSurfaceM2() * (float) $this->heightMeters;
    }

    public function getFloorLevel(): ?string { return $this->floorLevel; }
    public function setFloorLevel(?string $floorLevel): static { $this->floorLevel = $this->nullableString($floorLevel); return $this; }

    public function getLocationLabel(): ?string { return $this->locationLabel; }
    public function setLocationLabel(?string $locationLabel): static { $this->locationLabel = $this->nullableString($locationLabel); return $this; }

    public function getTargetTemperature(): ?string { return $this->targetTemperature; }
    public function setTargetTemperature(int|float|string|null $targetTemperature): static { $this->targetTemperature = $this->nullableDecimal($targetTemperature); return $this; }

    public function getMinTemperature(): ?string { return $this->minTemperature; }
    public function setMinTemperature(int|float|string|null $minTemperature): static { $this->minTemperature = $this->nullableDecimal($minTemperature); return $this; }

    public function getMaxTemperature(): ?string { return $this->maxTemperature; }
    public function setMaxTemperature(int|float|string|null $maxTemperature): static { $this->maxTemperature = $this->nullableDecimal($maxTemperature); return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int|string|null $sortOrder): static { $this->sortOrder = max(0, (int) $sortOrder); return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $this->nullableString($description); return $this; }

    public function getDisplayName(): string
    {
        return trim(sprintf('%s - %s', (string) $this->code, (string) $this->name), ' -');
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function decimal(int|float|string|null $value, int $scale = 2): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format(max(0, (float) $normalized), $scale, '.', '');
    }

    private function nullableDecimal(int|float|string|null $value, int $scale = 2): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, $scale, '.', '');
    }
}
