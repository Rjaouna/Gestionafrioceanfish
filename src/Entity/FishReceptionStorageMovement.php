<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\FishReceptionStorageMovementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FishReceptionStorageMovementRepository::class)]
#[ORM\Table(name: 'fish_reception_storage_movement')]
#[ORM\Index(name: 'idx_fish_storage_movement_reception', columns: ['reception_id'])]
#[ORM\Index(name: 'idx_fish_storage_movement_stage', columns: ['storage_stage'])]
#[ORM\Index(name: 'idx_fish_storage_movement_type', columns: ['movement_type'])]
#[ORM\Index(name: 'idx_fish_storage_movement_location', columns: ['location'])]
#[ORM\Index(name: 'idx_fish_storage_movement_date', columns: ['movement_date'])]
#[ORM\Index(name: 'idx_fish_storage_movement_created_by', columns: ['created_by_id'])]
class FishReceptionStorageMovement
{
    use TimestampableUserTrait;

    public const STAGE_INITIAL = 'initial';
    public const STAGE_FINAL = 'final';

    public const TYPE_INITIAL_ENTRY = 'initial_entry';
    public const TYPE_INITIAL_EXIT = 'initial_exit';
    public const TYPE_INITIAL_RETURN = 'initial_return';
    public const TYPE_FINAL_ENTRY = 'final_entry';
    public const TYPE_FINAL_EXIT = 'final_exit';

    public const TYPE_LABELS = [
        self::TYPE_INITIAL_ENTRY => 'Stockage initial',
        self::TYPE_INITIAL_EXIT => 'Sortie vers traitement',
        self::TYPE_INITIAL_RETURN => 'Retour au stock initial',
        self::TYPE_FINAL_ENTRY => 'Stockage final',
        self::TYPE_FINAL_EXIT => 'Sortie expedition',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FishReception::class, inversedBy: 'storageMovements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FishReception $reception = null;

    #[ORM\Column(length: 20)]
    private string $storageStage = self::STAGE_INITIAL;

    #[ORM\Column(length: 40)]
    private string $movementType = self::TYPE_INITIAL_ENTRY;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $location = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $quantityKg = '0.000';

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $movementDate = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $movementTime = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureChamber = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureProduct = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $note = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->movementDate = $now;
        $this->movementTime = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReception(): ?FishReception
    {
        return $this->reception;
    }

    public function setReception(?FishReception $reception): static
    {
        $this->reception = $reception;

        return $this;
    }

    public function getStorageStage(): string
    {
        return $this->storageStage;
    }

    public function setStorageStage(string $storageStage): static
    {
        if (!in_array($storageStage, [self::STAGE_INITIAL, self::STAGE_FINAL], true)) {
            throw new \InvalidArgumentException('Phase de stockage invalide.');
        }

        $this->storageStage = $storageStage;

        return $this;
    }

    public function getMovementType(): string
    {
        return $this->movementType;
    }

    public function setMovementType(string $movementType): static
    {
        if (!isset(self::TYPE_LABELS[$movementType])) {
            throw new \InvalidArgumentException('Type de mouvement stockage invalide.');
        }

        $this->movementType = $movementType;

        return $this;
    }

    public function getMovementTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->movementType] ?? $this->movementType;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = trim((string) $location);

        return $this;
    }

    public function getQuantityKg(): string
    {
        return $this->quantityKg;
    }

    public function setQuantityKg(int|float|string|null $quantityKg): static
    {
        $this->quantityKg = $this->decimal($quantityKg, 3, true);

        return $this;
    }

    public function getQuantityKgValue(): float
    {
        return (float) $this->quantityKg;
    }

    public function getAbsoluteQuantityKgValue(): float
    {
        return abs($this->getQuantityKgValue());
    }

    public function getMovementDate(): ?\DateTimeImmutable
    {
        return $this->movementDate;
    }

    public function setMovementDate(?\DateTimeImmutable $movementDate): static
    {
        $this->movementDate = $movementDate;

        return $this;
    }

    public function getMovementTime(): ?\DateTimeImmutable
    {
        return $this->movementTime;
    }

    public function setMovementTime(?\DateTimeImmutable $movementTime): static
    {
        $this->movementTime = $movementTime;

        return $this;
    }

    public function getMovementDateTime(): ?\DateTimeImmutable
    {
        if (!$this->movementDate instanceof \DateTimeImmutable) {
            return null;
        }

        if (!$this->movementTime instanceof \DateTimeImmutable) {
            return $this->movementDate->setTime(0, 0);
        }

        return $this->movementDate->setTime(
            (int) $this->movementTime->format('H'),
            (int) $this->movementTime->format('i'),
        );
    }

    public function getTemperatureChamber(): ?string
    {
        return $this->temperatureChamber;
    }

    public function setTemperatureChamber(int|float|string|null $temperatureChamber): static
    {
        $this->temperatureChamber = $this->nullableDecimal($temperatureChamber);

        return $this;
    }

    public function getTemperatureProduct(): ?string
    {
        return $this->temperatureProduct;
    }

    public function setTemperatureProduct(int|float|string|null $temperatureProduct): static
    {
        $this->temperatureProduct = $this->nullableDecimal($temperatureProduct);

        return $this;
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

    public function isEntryMovement(): bool
    {
        return $this->quantityKg !== '' && $this->getQuantityKgValue() > 0;
    }

    private function nullableDecimal(int|float|string|null $value, int $scale = 2): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return $this->decimal($value, $scale, true);
    }

    private function decimal(int|float|string|null $value, int $scale = 2, bool $allowNegative = false): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        $number = (float) $normalized;
        if (!$allowNegative) {
            $number = max(0.0, $number);
        }

        return number_format($number, $scale, '.', '');
    }
}
