<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_expense_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_expense_status', columns: ['status'])]
#[ORM\Index(name: 'idx_expense_date', columns: ['expense_date'])]
#[ORM\Index(name: 'idx_expense_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_expense_paid_by', columns: ['paid_by_id'])]
#[ORM\Index(name: 'idx_expense_validated_by', columns: ['validated_by_id'])]
#[ORM\Index(name: 'idx_expense_refused_by', columns: ['refused_by_id'])]
#[ORM\Index(name: 'idx_expense_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_expense_updated_by', columns: ['updated_by_id'])]
class Expense
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending_validation';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_PENDING => 'En attente de validation',
        self::STATUS_VALIDATED => 'Validée',
        self::STATUS_REFUSED => 'Refusée',
        self::STATUS_PAID => 'Payée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    public const STATUS_BADGES = [
        self::STATUS_DRAFT => 'text-bg-secondary',
        self::STATUS_PENDING => 'text-bg-warning',
        self::STATUS_VALIDATED => 'text-bg-primary',
        self::STATUS_REFUSED => 'text-bg-danger',
        self::STATUS_PAID => 'text-bg-success',
        self::STATUS_CANCELLED => 'text-bg-danger',
    ];

    public const PAYMENT_METHODS = [
        'Carte bancaire' => 'card',
        'Virement' => 'bank_transfer',
        'Prélèvement' => 'direct_debit',
        'Espèces' => 'cash',
        'Chèque' => 'check',
        'Autre' => 'other',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'card' => 'Carte bancaire',
        'bank_transfer' => 'Virement',
        'direct_debit' => 'Prélèvement',
        'cash' => 'Espèces',
        'check' => 'Chèque',
        'other' => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $title = null;

    #[ORM\Column(length: 80)]
    private ?string $reference = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $expenseDate = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $amountHt = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $vatRate = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $vatAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $amountTtc = '0.00';

    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class, inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ExpenseCategory $category = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private ?string $paymentMethod = 'bank_transfer';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $supplierName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $supplierEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $supplierPhone = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $paidBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refusedReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $refusedBy = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, ExpenseDocument> */
    #[ORM\OneToMany(targetEntity: ExpenseDocument::class, mappedBy: 'expense', orphanRemoval: true, cascade: ['persist'])]
    private Collection $documents;

    /** @var Collection<int, ExpenseShare> */
    #[ORM\OneToMany(targetEntity: ExpenseShare::class, mappedBy: 'expense', orphanRemoval: true, cascade: ['persist'])]
    private Collection $shares;

    public function __construct()
    {
        $this->expenseDate = new \DateTimeImmutable('today');
        $this->documents = new ArrayCollection();
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = trim($reference);

        return $this;
    }

    public function getExpenseDate(): ?\DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(?\DateTimeImmutable $expenseDate): static
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getAmountHt(): ?string
    {
        return $this->amountHt;
    }

    public function setAmountHt(int|float|string|null $amountHt): static
    {
        $this->amountHt = $this->decimal($amountHt);

        return $this;
    }

    public function getVatRate(): ?string
    {
        return $this->vatRate;
    }

    public function setVatRate(int|float|string|null $vatRate): static
    {
        $this->vatRate = $this->decimal($vatRate);

        return $this;
    }

    public function getVatAmount(): ?string
    {
        return $this->vatAmount;
    }

    public function setVatAmount(int|float|string|null $vatAmount): static
    {
        $this->vatAmount = $this->decimal($vatAmount);

        return $this;
    }

    public function getAmountTtc(): ?string
    {
        return $this->amountTtc;
    }

    public function setAmountTtc(int|float|string|null $amountTtc): static
    {
        $this->amountTtc = $this->decimal($amountTtc);

        return $this;
    }

    public function getCategory(): ?ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(?ExpenseCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = trim($paymentMethod);

        return $this;
    }

    public function getPaymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->paymentMethod] ?? 'Autre';
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function setSupplierName(string $supplierName): static
    {
        $this->supplierName = trim($supplierName);

        return $this;
    }

    public function getSupplierEmail(): ?string
    {
        return $this->supplierEmail;
    }

    public function setSupplierEmail(?string $supplierEmail): static
    {
        $this->supplierEmail = $supplierEmail ? trim($supplierEmail) : null;

        return $this;
    }

    public function getSupplierPhone(): ?string
    {
        return $this->supplierPhone;
    }

    public function setSupplierPhone(?string $supplierPhone): static
    {
        $this->supplierPhone = $supplierPhone ? trim($supplierPhone) : null;

        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber ? trim($invoiceNumber) : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Statut de dépense invalide.');
        }

        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClass(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'text-bg-light';
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(?User $paidBy): static
    {
        $this->paidBy = $paidBy;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;

        return $this;
    }

    public function getRefusedReason(): ?string
    {
        return $this->refusedReason;
    }

    public function setRefusedReason(?string $refusedReason): static
    {
        $this->refusedReason = $refusedReason ? trim($refusedReason) : null;

        return $this;
    }

    public function getRefusedBy(): ?User
    {
        return $this->refusedBy;
    }

    public function setRefusedBy(?User $refusedBy): static
    {
        $this->refusedBy = $refusedBy;

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

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** @return Collection<int, ExpenseDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    /** @return list<ExpenseDocument> */
    public function getActiveDocuments(): array
    {
        return $this->documents
            ->filter(static fn (ExpenseDocument $document): bool => $document->isActive())
            ->toArray();
    }

    public function addDocument(ExpenseDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setExpense($this);
        }

        return $this;
    }

    public function removeDocument(ExpenseDocument $document): static
    {
        if ($this->documents->removeElement($document) && $document->getExpense() === $this) {
            $document->setExpense(null);
        }

        return $this;
    }

    /** @return Collection<int, ExpenseShare> */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(ExpenseShare $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setExpense($this);
        }

        return $this;
    }

    public function removeShare(ExpenseShare $share): static
    {
        if ($this->shares->removeElement($share) && $share->getExpense() === $this) {
            $share->setExpense(null);
        }

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
