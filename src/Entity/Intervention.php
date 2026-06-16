<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterventionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
#[ORM\Table(name: 'intervention')]
#[ORM\Index(name: 'idx_intervention_status', columns: ['status'])]
#[ORM\Index(name: 'idx_intervention_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_intervention_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_intervention_contract', columns: ['contract_id'])]
#[ORM\Index(name: 'idx_intervention_intervenant', columns: ['intervenant_id'])]
#[ORM\Index(name: 'idx_intervention_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_intervention_updated_by', columns: ['updated_by_id'])]
class Intervention
{
    use TimestampableUserTrait;

    public const PRIORITIES = [
        'Basse' => 'basse',
        'Normale' => 'normale',
        'Haute' => 'haute',
        'Urgente' => 'urgente',
    ];

    public const STATUSES = [
        'À planifier' => 'a_planifier',
        'Planifiée' => 'planifiee',
        'En cours' => 'en_cours',
        'Terminée' => 'terminee',
        'Annulée' => 'annulee',
    ];

    public const RESULT_STATUSES = [
        'Résolu' => 'resolu',
        'Non résolu' => 'non_resolu',
        'À reprogrammer' => 'a_reprogrammer',
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

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $plannedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(length: 30)]
    private string $priority = 'normale';

    #[ORM\Column(length: 30)]
    private string $status = 'a_planifier';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $workDone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $resultStatus = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: MaintenanceContract::class, inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MaintenanceContract $contract = null;

    #[ORM\ManyToOne(targetEntity: Intervenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Intervenant $intervenant = null;

    /** @var Collection<int, InterventionIntervenant> */
    #[ORM\OneToMany(targetEntity: InterventionIntervenant::class, mappedBy: 'intervention', orphanRemoval: true, cascade: ['persist'])]
    private Collection $assignments;

    /** @var Collection<int, InterventionHistory> */
    #[ORM\OneToMany(targetEntity: InterventionHistory::class, mappedBy: 'intervention', orphanRemoval: true, cascade: ['persist'])]
    private Collection $histories;

    public function __construct()
    {
        $this->assignments = new ArrayCollection();
        $this->histories = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
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
    public function getPlannedAt(): ?\DateTimeImmutable { return $this->plannedAt; }
    public function setPlannedAt(?\DateTimeImmutable $plannedAt): static { $this->plannedAt = $plannedAt; return $this; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $startedAt): static { $this->startedAt = $startedAt; return $this; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(?\DateTimeImmutable $endedAt): static { $this->endedAt = $endedAt; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): static { $this->priority = $priority; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function getWorkDone(): ?string { return $this->workDone; }
    public function setWorkDone(?string $workDone): static { $workDone = trim((string) $workDone); $this->workDone = $workDone !== '' ? $workDone : null; return $this; }
    public function getInternalNotes(): ?string { return $this->internalNotes; }
    public function setInternalNotes(?string $internalNotes): static { $internalNotes = trim((string) $internalNotes); $this->internalNotes = $internalNotes !== '' ? $internalNotes : null; return $this; }
    public function getResultStatus(): ?string { return $this->resultStatus; }
    public function setResultStatus(?string $resultStatus): static { $resultStatus = trim((string) $resultStatus); $this->resultStatus = $resultStatus !== '' ? $resultStatus : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getContract(): ?MaintenanceContract { return $this->contract; }
    public function setContract(?MaintenanceContract $contract): static { $this->contract = $contract; return $this; }
    public function getIntervenant(): ?Intervenant { return $this->intervenant; }
    public function setIntervenant(?Intervenant $intervenant): static { $this->intervenant = $intervenant; return $this; }
    /** @return Collection<int, InterventionIntervenant> */
    public function getAssignments(): Collection { return $this->assignments; }
    public function addAssignment(InterventionIntervenant $assignment): static { if (!$this->assignments->contains($assignment)) { $this->assignments->add($assignment); $assignment->setIntervention($this); } return $this; }
    public function removeAssignment(InterventionIntervenant $assignment): static { if ($this->assignments->removeElement($assignment) && $assignment->getIntervention() === $this) { $assignment->setIntervention(null); } return $this; }
    /** @return Collection<int, InterventionHistory> */
    public function getHistories(): Collection { return $this->histories; }
}
