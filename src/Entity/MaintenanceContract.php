<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\MaintenanceContractRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaintenanceContractRepository::class)]
#[ORM\Table(name: 'maintenance_contract')]
#[ORM\Index(name: 'idx_maintenance_contract_status', columns: ['status'])]
#[ORM\Index(name: 'idx_maintenance_contract_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_maintenance_contract_intervenant', columns: ['intervenant_id'])]
#[ORM\Index(name: 'idx_maintenance_contract_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_maintenance_contract_updated_by', columns: ['updated_by_id'])]
class MaintenanceContract
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const FREQUENCIES = [
        'Mensuelle' => 'mensuelle',
        'Trimestrielle' => 'trimestrielle',
        'Semestrielle' => 'semestrielle',
        'Annuelle' => 'annuelle',
        'Personnalisée' => 'personnalisee',
    ];

    public const STATUSES = [
        'Brouillon' => 'brouillon',
        'Actif' => 'actif',
        'Suspendu' => 'suspendu',
        'Expiré' => 'expire',
        'Résilié' => 'resilie',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $reference = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $customerName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerAddress = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $contractType = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $renewalDate = null;

    #[ORM\Column(length: 30)]
    private string $interventionFrequency = 'annuelle';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: 30)]
    private string $status = 'brouillon';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Intervenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Intervenant $intervenant = null;

    /** @var Collection<int, Intervention> */
    #[ORM\OneToMany(targetEntity: Intervention::class, mappedBy: 'contract')]
    private Collection $interventions;

    public function __construct()
    {
        $this->interventions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = trim($reference); return $this; }
    public function getCustomerName(): ?string { return $this->customerName; }
    public function setCustomerName(string $customerName): static { $this->customerName = trim($customerName); return $this; }
    public function getCustomerEmail(): ?string { return $this->customerEmail; }
    public function setCustomerEmail(?string $customerEmail): static { $customerEmail = trim((string) $customerEmail); $this->customerEmail = $customerEmail !== '' ? mb_strtolower($customerEmail) : null; return $this; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function setCustomerPhone(?string $customerPhone): static { $customerPhone = trim((string) $customerPhone); $this->customerPhone = $customerPhone !== '' ? $customerPhone : null; return $this; }
    public function getCustomerAddress(): ?string { return $this->customerAddress; }
    public function setCustomerAddress(?string $customerAddress): static { $customerAddress = trim((string) $customerAddress); $this->customerAddress = $customerAddress !== '' ? $customerAddress : null; return $this; }
    public function getContractType(): ?string { return $this->contractType; }
    public function setContractType(?string $contractType): static { $contractType = trim((string) $contractType); $this->contractType = $contractType !== '' ? $contractType : null; return $this; }
    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }
    public function setStartDate(?\DateTimeImmutable $startDate): static { $this->startDate = $startDate; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $endDate): static { $this->endDate = $endDate; return $this; }
    public function getRenewalDate(): ?\DateTimeImmutable { return $this->renewalDate; }
    public function setRenewalDate(?\DateTimeImmutable $renewalDate): static { $this->renewalDate = $renewalDate; return $this; }
    public function getInterventionFrequency(): string { return $this->interventionFrequency; }
    public function setInterventionFrequency(string $interventionFrequency): static { $this->interventionFrequency = $interventionFrequency; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(null|float|string $amount): static { $this->amount = $amount !== null && $amount !== '' ? (string) $amount : null; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getIntervenant(): ?Intervenant { return $this->intervenant; }
    public function setIntervenant(?Intervenant $intervenant): static { $this->intervenant = $intervenant; return $this; }
    /** @return Collection<int, Intervention> */
    public function getInterventions(): Collection { return $this->interventions; }
}
