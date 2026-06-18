<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\AppointmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointment')]
#[ORM\UniqueConstraint(name: 'uniq_appointment_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_appointment_start', columns: ['start_at'])]
#[ORM\Index(name: 'idx_appointment_end', columns: ['end_at'])]
#[ORM\Index(name: 'idx_appointment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_appointment_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_appointment_type', columns: ['appointment_type'])]
#[ORM\Index(name: 'idx_appointment_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_appointment_deleted', columns: ['is_deleted'])]
#[ORM\Index(name: 'idx_appointment_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_appointment_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_appointment_deleted_by', columns: ['deleted_by_id'])]
class Appointment
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const STATUS_CHOICES = [
        'Planifié' => 'planned',
        'Confirmé' => 'confirmed',
        'En attente' => 'pending',
        'Terminé' => 'completed',
        'Annulé' => 'cancelled',
        'Reporté' => 'postponed',
    ];

    public const STATUS_LABELS = [
        'planned' => 'Planifié',
        'confirmed' => 'Confirmé',
        'pending' => 'En attente',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
        'postponed' => 'Reporté',
    ];

    public const PRIORITY_CHOICES = [
        'Basse' => 'low',
        'Normale' => 'normal',
        'Haute' => 'high',
        'Urgente' => 'urgent',
    ];

    public const PRIORITY_LABELS = [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'urgent' => 'Urgente',
    ];

    public const TYPE_CHOICES = [
        'Client' => 'client',
        'Interne' => 'internal',
        'Fournisseur' => 'supplier',
        'Technique' => 'technical',
        'Administratif' => 'administrative',
        'Autre' => 'other',
    ];

    public const TYPE_LABELS = [
        'client' => 'Client',
        'internal' => 'Interne',
        'supplier' => 'Fournisseur',
        'technical' => 'Technique',
        'administrative' => 'Administratif',
        'other' => 'Autre',
    ];

    public const STATUS_COLORS = [
        'planned' => '#0d6efd',
        'confirmed' => '#198754',
        'pending' => '#ffc107',
        'completed' => '#20c997',
        'cancelled' => '#dc3545',
        'postponed' => '#6f42c1',
    ];

    public const PRIORITY_COLORS = [
        'low' => '#6c757d',
        'normal' => '#0d6efd',
        'high' => '#fd7e14',
        'urgent' => '#dc3545',
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
    #[Assert\Length(max: 80)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 255)]
    private ?string $meetingLink = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startAt', message: 'La fin doit être après le début.')]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $allDay = false;

    #[ORM\Column(length: 30)]
    private string $status = 'planned';

    #[ORM\Column(length: 30)]
    private string $priority = 'normal';

    #[ORM\Column(length: 40)]
    private string $appointmentType = 'client';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $customerName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $customerPhone = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderAt = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $color = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, AppointmentParticipant> */
    #[ORM\OneToMany(targetEntity: AppointmentParticipant::class, mappedBy: 'appointment', orphanRemoval: true, cascade: ['persist'])]
    private Collection $participants;

    /** @var Collection<int, AppointmentHistory> */
    #[ORM\OneToMany(targetEntity: AppointmentHistory::class, mappedBy: 'appointment', orphanRemoval: true, cascade: ['persist'])]
    private Collection $histories;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->histories = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = trim($reference); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): static { $location = trim((string) $location); $this->location = $location !== '' ? $location : null; return $this; }
    public function getMeetingLink(): ?string { return $this->meetingLink; }
    public function setMeetingLink(?string $meetingLink): static { $meetingLink = trim((string) $meetingLink); $this->meetingLink = $meetingLink !== '' ? $meetingLink : null; return $this; }
    public function getStartAt(): ?\DateTimeImmutable { return $this->startAt; }
    public function setStartAt(?\DateTimeImmutable $startAt): static { $this->startAt = $startAt; return $this; }
    public function getEndAt(): ?\DateTimeImmutable { return $this->endAt; }
    public function setEndAt(?\DateTimeImmutable $endAt): static { $this->endAt = $endAt; return $this; }
    public function isAllDay(): bool { return $this->allDay; }
    public function setAllDay(bool $allDay): static { $this->allDay = $allDay; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): static { $this->priority = $priority; return $this; }
    public function getAppointmentType(): string { return $this->appointmentType; }
    public function setAppointmentType(string $appointmentType): static { $this->appointmentType = $appointmentType; return $this; }
    public function getCustomerName(): ?string { return $this->customerName; }
    public function setCustomerName(?string $customerName): static { $customerName = trim((string) $customerName); $this->customerName = $customerName !== '' ? $customerName : null; return $this; }
    public function getCustomerEmail(): ?string { return $this->customerEmail; }
    public function setCustomerEmail(?string $customerEmail): static { $customerEmail = trim((string) $customerEmail); $this->customerEmail = $customerEmail !== '' ? mb_strtolower($customerEmail) : null; return $this; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function setCustomerPhone(?string $customerPhone): static { $customerPhone = trim((string) $customerPhone); $this->customerPhone = $customerPhone !== '' ? $customerPhone : null; return $this; }
    public function getReminderAt(): ?\DateTimeImmutable { return $this->reminderAt; }
    public function setReminderAt(?\DateTimeImmutable $reminderAt): static { $this->reminderAt = $reminderAt; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $color = trim((string) $color); $this->color = $color !== '' ? $color : null; return $this; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }
    public function setCancellationReason(?string $cancellationReason): static { $cancellationReason = trim((string) $cancellationReason); $this->cancellationReason = $cancellationReason !== '' ? $cancellationReason : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    /** @return Collection<int, AppointmentParticipant> */
    public function getParticipants(): Collection { return $this->participants; }
    public function addParticipant(AppointmentParticipant $participant): static { if (!$this->participants->contains($participant)) { $this->participants->add($participant); $participant->setAppointment($this); } return $this; }
    public function removeParticipant(AppointmentParticipant $participant): static { if ($this->participants->removeElement($participant) && $participant->getAppointment() === $this) { $participant->setAppointment(null); } return $this; }

    /** @return Collection<int, AppointmentHistory> */
    public function getHistories(): Collection { return $this->histories; }
    public function addHistory(AppointmentHistory $history): static { if (!$this->histories->contains($history)) { $this->histories->add($history); $history->setAppointment($this); } return $this; }

    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getPriorityLabel(): string { return self::PRIORITY_LABELS[$this->priority] ?? $this->priority; }
    public function getAppointmentTypeLabel(): string { return self::TYPE_LABELS[$this->appointmentType] ?? $this->appointmentType; }
    public function getCalendarColor(): string { return $this->color ?: (self::PRIORITY_COLORS[$this->priority] ?? self::STATUS_COLORS[$this->status] ?? '#0d6efd'); }
}
