<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\WasteSaleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WasteSaleRepository::class)]
#[ORM\Table(name: 'waste_sale')]
#[ORM\UniqueConstraint(name: 'uniq_waste_sale_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_waste_sale_date', columns: ['sale_date'])]
#[ORM\Index(name: 'idx_waste_sale_buyer', columns: ['buyer_name'])]
#[ORM\Index(name: 'idx_waste_sale_payment', columns: ['payment_method'])]
#[ORM\Index(name: 'idx_waste_sale_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_waste_sale_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_waste_sale_deleted_by', columns: ['deleted_by_id'])]
class WasteSale
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const DEFAULT_UNIT_PRICE = 0.60;

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_CHECK = 'check';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_CREDIT = 'credit';
    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Especes',
        self::PAYMENT_METHOD_CHECK => 'Cheque',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Virement',
        self::PAYMENT_METHOD_CREDIT => 'Credit client',
        self::PAYMENT_METHOD_OTHER => 'Autre',
    ];

    public const PAYMENT_METHOD_CHOICES = [
        'Especes' => self::PAYMENT_METHOD_CASH,
        'Cheque' => self::PAYMENT_METHOD_CHECK,
        'Virement' => self::PAYMENT_METHOD_BANK_TRANSFER,
        'Credit client' => self::PAYMENT_METHOD_CREDIT,
        'Autre' => self::PAYMENT_METHOD_OTHER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $reference = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $saleDate = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $buyerName = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $paymentMethod = self::PAYMENT_METHOD_CASH;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\Positive(message: 'Le poids doit etre superieur a 0.')]
    private string $weightKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.60'])]
    #[Assert\Positive]
    private string $unitPrice = '0.60';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->saleDate = new \DateTimeImmutable('today');
        $this->unitPrice = $this->decimal(self::DEFAULT_UNIT_PRICE, 2);
        $this->recalculateTotal();
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

    public function getSaleDate(): ?\DateTimeImmutable
    {
        return $this->saleDate;
    }

    public function setSaleDate(?\DateTimeImmutable $saleDate): static
    {
        $this->saleDate = $saleDate;

        return $this;
    }

    public function getBuyerName(): ?string
    {
        return $this->buyerName;
    }

    public function setBuyerName(?string $buyerName): static
    {
        $buyerName = trim((string) $buyerName);
        $this->buyerName = $buyerName !== '' ? mb_convert_case($buyerName, MB_CASE_TITLE, 'UTF-8') : null;

        return $this;
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
            throw new \InvalidArgumentException('Mode de paiement vente dechets invalide.');
        }

        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->paymentMethod] ?? $this->paymentMethod;
    }

    public function getPaymentMethodBadgeClass(): string
    {
        return match ($this->paymentMethod) {
            self::PAYMENT_METHOD_CASH => 'text-bg-success',
            self::PAYMENT_METHOD_CHECK => 'text-bg-primary',
            self::PAYMENT_METHOD_BANK_TRANSFER => 'text-bg-info',
            self::PAYMENT_METHOD_CREDIT => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public function getWeightKg(): string
    {
        return $this->weightKg;
    }

    public function setWeightKg(int|float|string|null $weightKg): static
    {
        $this->weightKg = $this->decimal($weightKg, 3);
        $this->recalculateTotal();

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int|float|string|null $unitPrice): static
    {
        $this->unitPrice = $this->decimal($unitPrice, 2);
        if ($this->unitPriceValue() <= 0) {
            $this->unitPrice = $this->decimal(self::DEFAULT_UNIT_PRICE, 2);
        }
        $this->recalculateTotal();

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int|float|string|null $totalAmount): static
    {
        $this->totalAmount = $this->decimal($totalAmount, 2);

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

    public function weightKgValue(): float
    {
        return (float) $this->weightKg;
    }

    public function unitPriceValue(): float
    {
        return (float) $this->unitPrice;
    }

    public function totalAmountValue(): float
    {
        return (float) $this->totalAmount;
    }

    public function recalculateTotal(): static
    {
        $this->totalAmount = $this->decimal($this->weightKgValue() * $this->unitPriceValue(), 2);

        return $this;
    }

    private function decimal(int|float|string|null $value, int $scale): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        $normalized = str_replace(',', '.', (string) $value);
        $float = max(0.0, (float) $normalized);

        return number_format($float, $scale, '.', '');
    }
}
