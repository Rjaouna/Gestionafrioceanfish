<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\CashFundTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CashFundTransactionRepository::class)]
#[ORM\Table(name: 'cash_fund_transaction')]
#[ORM\UniqueConstraint(name: 'uniq_cash_fund_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_cash_fund_type', columns: ['type'])]
#[ORM\Index(name: 'idx_cash_fund_date', columns: ['movement_date'])]
#[ORM\Index(name: 'idx_cash_fund_expense', columns: ['expense_id'])]
#[ORM\Index(name: 'idx_cash_fund_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_cash_fund_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_cash_fund_deleted_by', columns: ['deleted_by_id'])]
class CashFundTransaction
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const TYPE_FUNDING = 'funding';
    public const TYPE_EXPENSE_PAYMENT = 'expense_payment';
    public const TYPE_EXPENSE_REVERSAL = 'expense_reversal';

    public const TYPE_LABELS = [
        self::TYPE_FUNDING => 'Alimentation',
        self::TYPE_EXPENSE_PAYMENT => 'Depense payee',
        self::TYPE_EXPENSE_REVERSAL => 'Annulation depense',
    ];

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_CHECK = 'check';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_METHOD_CHOICES = [
        'Especes' => self::PAYMENT_METHOD_CASH,
        'Cheque' => self::PAYMENT_METHOD_CHECK,
        'Virement' => self::PAYMENT_METHOD_BANK_TRANSFER,
        'Autre' => self::PAYMENT_METHOD_OTHER,
    ];

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Especes',
        self::PAYMENT_METHOD_CHECK => 'Cheque',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Virement',
        self::PAYMENT_METHOD_OTHER => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $reference = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $movementDate = null;

    #[ORM\Column(length: 40)]
    private string $type = self::TYPE_FUNDING;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\NotBlank]
    private string $amount = '0.00';

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $paymentMethod = self::PAYMENT_METHOD_CASH;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $sourceName = 'Patron';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Expense::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Expense $expense = null;

    public function __construct()
    {
        $this->movementDate = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $reference = strtoupper(trim((string) $reference));
        $this->reference = $reference !== '' ? $reference : null;

        return $this;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!isset(self::TYPE_LABELS[$type])) {
            throw new \InvalidArgumentException('Type de mouvement cagnotte invalide.');
        }

        $this->type = $type;

        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_FUNDING => 'text-bg-success',
            self::TYPE_EXPENSE_PAYMENT => 'text-bg-danger',
            self::TYPE_EXPENSE_REVERSAL => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(int|float|string|null $amount): static
    {
        $this->amount = $this->decimal($amount);

        return $this;
    }

    public function amountValue(): float
    {
        return (float) $this->amount;
    }

    public function absoluteAmountValue(): float
    {
        return abs($this->amountValue());
    }

    public function isInflow(): bool
    {
        return $this->amountValue() >= 0;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $paymentMethod = trim((string) $paymentMethod);
        if ($paymentMethod === '') {
            $paymentMethod = self::PAYMENT_METHOD_CASH;
        }

        if (!isset(self::PAYMENT_METHOD_LABELS[$paymentMethod])) {
            throw new \InvalidArgumentException('Moyen de paiement cagnotte invalide.');
        }

        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->paymentMethod] ?? $this->paymentMethod;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): static
    {
        $sourceName = trim((string) $sourceName);
        $this->sourceName = $sourceName !== '' ? $sourceName : null;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $notes = trim((string) $notes);
        $this->notes = $notes !== '' ? $notes : null;

        return $this;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): static
    {
        $this->expense = $expense;

        return $this;
    }

    private function decimal(int|float|string|null $value): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
