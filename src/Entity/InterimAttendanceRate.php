<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimAttendanceRateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterimAttendanceRateRepository::class)]
#[ORM\Table(name: 'interim_attendance_rate')]
#[ORM\UniqueConstraint(name: 'uniq_interim_attendance_rate_code', fields: ['code'])]
#[ORM\Index(name: 'idx_interim_attendance_rate_mode', columns: ['mode'])]
#[ORM\Index(name: 'idx_interim_attendance_rate_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_attendance_rate_updated_by', columns: ['updated_by_id'])]
class InterimAttendanceRate
{
    use TimestampableUserTrait;

    public const CODE_HOURLY_DEFAULT = 'hourly_default';
    public const CODE_TASK_CLEANING = 'task_cleaning';
    public const CODE_TASK_BOXING = 'task_boxing';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    private string $mode = InterimAttendance::MODE_HOURLY;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $unitLabel = 'heure';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $amount = '0.00';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = mb_strtolower(trim($code)); return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): static { $this->label = trim($label); return $this; }

    public function getMode(): string { return $this->mode; }
    public function setMode(string $mode): static
    {
        $this->mode = isset(InterimAttendance::MODE_LABELS[$mode]) ? $mode : InterimAttendance::MODE_HOURLY;

        return $this;
    }

    public function getModeLabel(): string { return InterimAttendance::MODE_LABELS[$this->mode] ?? $this->mode; }

    public function getUnitLabel(): string { return $this->unitLabel; }
    public function setUnitLabel(string $unitLabel): static { $this->unitLabel = trim($unitLabel) ?: 'unite'; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function getAmountValue(): float { return (float) $this->amount; }
    public function setAmount(string|float|int|null $amount): static
    {
        $this->amount = number_format(max(0.0, (float) str_replace(',', '.', (string) $amount)), 2, '.', '');

        return $this;
    }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
}
