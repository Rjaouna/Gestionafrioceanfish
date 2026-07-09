<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\GeneratedContractRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GeneratedContractRepository::class)]
#[ORM\Table(name: 'generated_contract')]
#[ORM\UniqueConstraint(name: 'uniq_generated_contract_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_generated_contract_date', columns: ['contract_date'])]
#[ORM\Index(name: 'idx_generated_contract_type', columns: ['contract_type'])]
#[ORM\Index(name: 'idx_generated_contract_status', columns: ['status'])]
#[ORM\Index(name: 'idx_generated_contract_client', columns: ['client_company_name'])]
class GeneratedContract
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const TYPE_CONDITIONING = 'conditioning';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATED = 'generated';

    public const TYPE_LABELS = [
        self::TYPE_CONDITIONING => 'Contrat de conditionnement',
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'A generer',
        self::STATUS_GENERATED => 'PDF genere',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $reference = null;

    #[ORM\Column(length: 50)]
    private string $contractType = self::TYPE_CONDITIONING;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $contractDate = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private ?string $campaign = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $clientCompanyName = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 1200)]
    private ?string $clientAddress = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $representativeTitle = 'Monsieur';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $representativeName = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $representativeIdNumber = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $signingCity = 'Casablanca';

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastGeneratedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $lastGeneratedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1200)]
    private ?string $internalNotes = null;

    public function __construct()
    {
        $today = new \DateTimeImmutable('today');
        $this->contractDate = $today;
        $this->campaign = sprintf('%s/%s', $today->format('Y'), $today->modify('+1 year')->format('Y'));
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

    public function getContractType(): string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): static
    {
        if (!isset(self::TYPE_LABELS[$contractType])) {
            throw new \InvalidArgumentException('Type de contrat invalide.');
        }
        $this->contractType = $contractType;

        return $this;
    }

    public function getContractTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->contractType] ?? $this->contractType;
    }

    public function getContractDate(): ?\DateTimeImmutable
    {
        return $this->contractDate;
    }

    public function setContractDate(?\DateTimeImmutable $contractDate): static
    {
        $this->contractDate = $contractDate;

        return $this;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): static
    {
        $campaign = trim((string) $campaign);
        $this->campaign = $campaign !== '' ? $campaign : null;

        return $this;
    }

    public function getClientCompanyName(): ?string
    {
        return $this->clientCompanyName;
    }

    public function setClientCompanyName(?string $clientCompanyName): static
    {
        $clientCompanyName = trim((string) $clientCompanyName);
        $this->clientCompanyName = $clientCompanyName !== '' ? $clientCompanyName : null;

        return $this;
    }

    public function getClientAddress(): ?string
    {
        return $this->clientAddress;
    }

    public function setClientAddress(?string $clientAddress): static
    {
        $clientAddress = trim((string) $clientAddress);
        $this->clientAddress = $clientAddress !== '' ? $clientAddress : null;

        return $this;
    }

    public function getRepresentativeTitle(): string
    {
        return $this->representativeTitle;
    }

    public function setRepresentativeTitle(string $representativeTitle): static
    {
        $representativeTitle = trim($representativeTitle);
        if (!in_array($representativeTitle, ['Monsieur', 'Madame'], true)) {
            throw new \InvalidArgumentException('Civilite du representant invalide.');
        }
        $this->representativeTitle = $representativeTitle;

        return $this;
    }

    public function getRepresentativeName(): ?string
    {
        return $this->representativeName;
    }

    public function setRepresentativeName(?string $representativeName): static
    {
        $representativeName = trim((string) $representativeName);
        $this->representativeName = $representativeName !== '' ? $representativeName : null;

        return $this;
    }

    public function getRepresentativeIdNumber(): ?string
    {
        return $this->representativeIdNumber;
    }

    public function setRepresentativeIdNumber(?string $representativeIdNumber): static
    {
        $representativeIdNumber = strtoupper(trim((string) $representativeIdNumber));
        $this->representativeIdNumber = $representativeIdNumber !== '' ? $representativeIdNumber : null;

        return $this;
    }

    public function getSigningCity(): string
    {
        return $this->signingCity;
    }

    public function setSigningCity(string $signingCity): static
    {
        $this->signingCity = trim($signingCity);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Statut de contrat invalide.');
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
        return $this->status === self::STATUS_GENERATED ? 'text-bg-success' : 'text-bg-warning';
    }

    public function getLastGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->lastGeneratedAt;
    }

    public function setLastGeneratedAt(?\DateTimeImmutable $lastGeneratedAt): static
    {
        $this->lastGeneratedAt = $lastGeneratedAt;

        return $this;
    }

    public function getLastGeneratedBy(): ?User
    {
        return $this->lastGeneratedBy;
    }

    public function setLastGeneratedBy(?User $lastGeneratedBy): static
    {
        $this->lastGeneratedBy = $lastGeneratedBy;

        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): static
    {
        $internalNotes = trim((string) $internalNotes);
        $this->internalNotes = $internalNotes !== '' ? $internalNotes : null;

        return $this;
    }
}
