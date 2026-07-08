<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimAttendanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterimAttendanceRepository::class)]
#[ORM\Table(name: 'interim_attendance')]
#[ORM\Index(name: 'idx_interim_attendance_worker', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_interim_attendance_date', columns: ['attendance_date'])]
#[ORM\Index(name: 'idx_interim_attendance_mode', columns: ['mode'])]
#[ORM\Index(name: 'idx_interim_attendance_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_attendance_updated_by', columns: ['updated_by_id'])]
class InterimAttendance
{
    use TimestampableUserTrait;

    public const MODE_HOURLY = 'hourly';
    public const MODE_TASK = 'task';
    public const TASK_CLEANING_ANCHOVY = 'cleaning_anchovy';
    public const TASK_BOXING_FILETS = 'boxing_filets';

    public const CLEANING_BOX_WEIGHT_KG = 10.0;
    public const CLEANING_RATE_WEIGHT_KG = 30.0;

    public const TASK_LABELS = [
        self::TASK_CLEANING_ANCHOVY => 'Nettoyage anchois',
        self::TASK_BOXING_FILETS => 'Mise en caisse filets',
    ];

    public const MODE_LABELS = [
        self::MODE_HOURLY => 'A l heure',
        self::MODE_TASK => 'A la tache',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InterimWorker::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InterimWorker $worker = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('today', message: 'La date de pointage ne peut pas etre dans le futur.')]
    private ?\DateTimeImmutable $attendanceDate = null;

    #[ORM\Column(length: 20)]
    private string $mode = self::MODE_HOURLY;

    #[ORM\Column(options: ['default' => true])]
    private bool $morningPresent = true;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $morningStart = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $morningEnd = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $afternoonPresent = true;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $afternoonStart = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $afternoonEnd = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $hourlyRate = '0.00';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $taskType = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $taskUnit = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, nullable: true)]
    private ?string $taskQuantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $taskUnitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $totalHours = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->attendanceDate = new \DateTimeImmutable('today');
        $this->morningStart = new \DateTimeImmutable('08:00');
        $this->morningEnd = new \DateTimeImmutable('13:00');
        $this->afternoonStart = new \DateTimeImmutable('13:00');
        $this->afternoonEnd = new \DateTimeImmutable('17:00');
    }

    public function getId(): ?int { return $this->id; }

    public function getWorker(): ?InterimWorker { return $this->worker; }
    public function setWorker(?InterimWorker $worker): static { $this->worker = $worker; return $this; }

    public function getAttendanceDate(): ?\DateTimeImmutable { return $this->attendanceDate; }
    public function setAttendanceDate(?\DateTimeImmutable $attendanceDate): static { $this->attendanceDate = $attendanceDate; return $this; }

    public function getMode(): string { return $this->mode; }
    public function setMode(string $mode): static
    {
        $this->mode = isset(self::MODE_LABELS[$mode]) ? $mode : self::MODE_HOURLY;

        return $this;
    }
    public function getModeLabel(): string { return self::MODE_LABELS[$this->mode] ?? $this->mode; }

    public function isMorningPresent(): bool { return $this->morningPresent; }
    public function setMorningPresent(bool $morningPresent): static { $this->morningPresent = $morningPresent; return $this; }

    public function getMorningStart(): ?\DateTimeImmutable { return $this->morningStart; }
    public function setMorningStart(?\DateTimeImmutable $morningStart): static { $this->morningStart = $morningStart; return $this; }

    public function getMorningEnd(): ?\DateTimeImmutable { return $this->morningEnd; }
    public function setMorningEnd(?\DateTimeImmutable $morningEnd): static { $this->morningEnd = $morningEnd; return $this; }

    public function isAfternoonPresent(): bool { return $this->afternoonPresent; }
    public function setAfternoonPresent(bool $afternoonPresent): static { $this->afternoonPresent = $afternoonPresent; return $this; }

    public function getAfternoonStart(): ?\DateTimeImmutable { return $this->afternoonStart; }
    public function setAfternoonStart(?\DateTimeImmutable $afternoonStart): static { $this->afternoonStart = $afternoonStart; return $this; }

    public function getAfternoonEnd(): ?\DateTimeImmutable { return $this->afternoonEnd; }
    public function setAfternoonEnd(?\DateTimeImmutable $afternoonEnd): static { $this->afternoonEnd = $afternoonEnd; return $this; }

    public function getHourlyRate(): string { return $this->hourlyRate; }
    public function getHourlyRateValue(): float { return (float) $this->hourlyRate; }
    public function setHourlyRate(string|float|int|null $hourlyRate): static
    {
        $this->hourlyRate = number_format(max(0.0, (float) str_replace(',', '.', (string) $hourlyRate)), 2, '.', '');

        return $this;
    }

    public function getTaskType(): ?string { return $this->taskType; }
    public function setTaskType(?string $taskType): static
    {
        $this->taskType = $this->nullableString($taskType);

        return $this;
    }
    public function getTaskTypeLabel(): string { return self::TASK_LABELS[$this->taskType ?? ''] ?? ($this->taskType ?: '-'); }

    public function getTaskUnit(): ?string { return $this->taskUnit; }
    public function setTaskUnit(?string $taskUnit): static { $this->taskUnit = $this->nullableString($taskUnit); return $this; }

    public function getTaskQuantity(): ?string { return $this->taskQuantity; }
    public function getTaskQuantityValue(): float { return (float) ($this->taskQuantity ?? 0); }
    public function setTaskQuantity(string|float|int|null $taskQuantity): static
    {
        $this->taskQuantity = $taskQuantity === null || trim((string) $taskQuantity) === '' ? null : number_format(max(0.0, (float) str_replace(',', '.', (string) $taskQuantity)), 3, '.', '');

        return $this;
    }

    public function getTaskUnitPrice(): ?string { return $this->taskUnitPrice; }
    public function getTaskUnitPriceValue(): float { return (float) ($this->taskUnitPrice ?? 0); }
    public function setTaskUnitPrice(string|float|int|null $taskUnitPrice): static
    {
        $this->taskUnitPrice = $taskUnitPrice === null || trim((string) $taskUnitPrice) === '' ? null : number_format(max(0.0, (float) str_replace(',', '.', (string) $taskUnitPrice)), 2, '.', '');

        return $this;
    }

    public function getTotalHours(): string { return $this->totalHours; }
    public function getTotalHoursValue(): float { return (float) $this->totalHours; }
    public function setTotalHours(string|float|int|null $totalHours): static
    {
        $this->totalHours = number_format(max(0.0, (float) str_replace(',', '.', (string) $totalHours)), 2, '.', '');

        return $this;
    }

    public function getTotalAmount(): string { return $this->totalAmount; }
    public function getTotalAmountValue(): float { return (float) $this->totalAmount; }
    public function setTotalAmount(string|float|int|null $totalAmount): static
    {
        $this->totalAmount = number_format(max(0.0, (float) str_replace(',', '.', (string) $totalAmount)), 2, '.', '');

        return $this;
    }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $this->nullableString($comment); return $this; }

    public function getPresenceLabel(): string
    {
        if ($this->mode === self::MODE_TASK) {
            return $this->getTaskTypeLabel();
        }

        if ($this->morningPresent && $this->afternoonPresent) {
            return 'Journee complete';
        }
        if ($this->morningPresent) {
            return 'Matin seulement';
        }
        if ($this->afternoonPresent) {
            return 'Apres-midi seulement';
        }

        return 'Absent';
    }

    public function getTimeRangeLabel(): string
    {
        if ($this->mode === self::MODE_TASK) {
            return $this->getTaskDetailsLabel();
        }

        $parts = [];
        if ($this->morningPresent) {
            $parts[] = sprintf('Matin %s-%s', $this->formatTime($this->morningStart), $this->formatTime($this->morningEnd));
        } else {
            $parts[] = 'Matin absent';
        }
        if ($this->afternoonPresent) {
            $parts[] = sprintf('AM %s-%s', $this->formatTime($this->afternoonStart), $this->formatTime($this->afternoonEnd));
        } else {
            $parts[] = 'AM absent';
        }

        return implode(' | ', $parts);
    }

    public function getTaskWeightKgValue(): float
    {
        if ($this->taskType === self::TASK_CLEANING_ANCHOVY) {
            return $this->getTaskQuantityValue() * self::CLEANING_BOX_WEIGHT_KG;
        }

        if ($this->taskType === self::TASK_BOXING_FILETS) {
            return $this->getTaskQuantityValue();
        }

        return 0.0;
    }

    public function getTaskDetailsLabel(): string
    {
        if ($this->taskType === self::TASK_CLEANING_ANCHOVY) {
            return sprintf(
                '%s caisse%s - %s kg',
                number_format($this->getTaskQuantityValue(), 0, ',', ' '),
                $this->getTaskQuantityValue() > 1 ? 's' : '',
                number_format($this->getTaskWeightKgValue(), 0, ',', ' '),
            );
        }

        if ($this->taskType === self::TASK_BOXING_FILETS) {
            return sprintf('%s kg', number_format($this->getTaskQuantityValue(), 3, ',', ' '));
        }

        return '-';
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function formatTime(?\DateTimeImmutable $time): string
    {
        return $time?->format('H:i') ?? '--:--';
    }
}
