<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimPaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterimPaymentRepository::class)]
#[ORM\Table(name: 'interim_payment')]
#[ORM\Index(name: 'idx_interim_payment_worker', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_interim_payment_date', columns: ['payment_date'])]
#[ORM\Index(name: 'idx_interim_payment_period', columns: ['period_from', 'period_to'])]
#[ORM\Index(name: 'idx_interim_payment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_interim_payment_method', columns: ['payment_method'])]
#[ORM\Index(name: 'idx_interim_payment_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_payment_updated_by', columns: ['updated_by_id'])]
class InterimPayment
{
    use TimestampableUserTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'A payer',
        self::STATUS_PAID => 'Paye',
        self::STATUS_CANCELLED => 'Annule',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING => 'text-bg-warning',
        self::STATUS_PAID => 'text-bg-success',
        self::STATUS_CANCELLED => 'text-bg-secondary',
    ];

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_OTHER = 'other';

    public const METHOD_LABELS = [
        self::METHOD_CASH => 'Especes',
        self::METHOD_BANK_TRANSFER => 'Virement',
        self::METHOD_CHEQUE => 'Cheque',
        self::METHOD_OTHER => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InterimWorker::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull]
    private ?InterimWorker $worker = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $paymentDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodTo = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\Positive(message: 'Le montant du paiement doit etre superieur a zero.')]
    private string $amount = '0.00';

    #[ORM\Column(length: 40)]
    #[Assert\Choice(choices: [self::METHOD_CASH, self::METHOD_BANK_TRANSFER, self::METHOD_CHEQUE, self::METHOD_OTHER])]
    private string $paymentMethod = self::METHOD_CASH;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELLED])]
    private string $status = self::STATUS_PAID;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $note = null;

    public function __construct()
    {
        $this->paymentDate = new \DateTimeImmutable('today');
        $this->periodFrom = new \DateTimeImmutable('first day of this month');
        $this->periodTo = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }

    public function getWorker(): ?InterimWorker { return $this->worker; }
    public function setWorker(?InterimWorker $worker): static { $this->worker = $worker; return $this; }

    public function getPaymentDate(): ?\DateTimeImmutable { return $this->paymentDate; }
    public function setPaymentDate(?\DateTimeImmutable $paymentDate): static { $this->paymentDate = $paymentDate; return $this; }

    public function getPeriodFrom(): ?\DateTimeImmutable { return $this->periodFrom; }
    public function setPeriodFrom(?\DateTimeImmutable $periodFrom): static { $this->periodFrom = $periodFrom; return $this; }

    public function getPeriodTo(): ?\DateTimeImmutable { return $this->periodTo; }
    public function setPeriodTo(?\DateTimeImmutable $periodTo): static { $this->periodTo = $periodTo; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function getAmountValue(): float { return (float) $this->amount; }
    public function setAmount(string|float|int|null $amount): static
    {
        $normalized = str_replace(',', '.', trim((string) ($amount ?? '0')));
        $this->amount = number_format(max(0.0, is_numeric($normalized) ? (float) $normalized : 0.0), 2, '.', '');

        return $this;
    }

    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = isset(self::METHOD_LABELS[$paymentMethod]) ? $paymentMethod : self::METHOD_OTHER;

        return $this;
    }
    public function getPaymentMethodLabel(): string { return self::METHOD_LABELS[$this->paymentMethod] ?? $this->paymentMethod; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        $this->status = isset(self::STATUS_LABELS[$status]) ? $status : self::STATUS_PAID;

        return $this;
    }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getStatusBadgeClass(): string { return self::STATUS_BADGES[$this->status] ?? 'text-bg-secondary'; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $this->nullableString($reference); return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $this->nullableString($note); return $this; }

    public function getWorkerName(): string
    {
        return $this->worker instanceof InterimWorker ? $this->worker->getFullName() : 'Interimaire supprime';
    }

    public function getPeriodLabel(): string
    {
        if (!$this->periodFrom instanceof \DateTimeImmutable && !$this->periodTo instanceof \DateTimeImmutable) {
            return '-';
        }

        return sprintf(
            '%s - %s',
            $this->periodFrom?->format('d/m/Y') ?? '-',
            $this->periodTo?->format('d/m/Y') ?? '-',
        );
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
