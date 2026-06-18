<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\AppointmentParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentParticipantRepository::class)]
#[ORM\Table(name: 'appointment_participant')]
#[ORM\UniqueConstraint(name: 'uniq_appointment_participant_user', fields: ['appointment', 'user'])]
#[ORM\Index(name: 'idx_appointment_participant_appointment', columns: ['appointment_id'])]
#[ORM\Index(name: 'idx_appointment_participant_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_appointment_participant_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_appointment_participant_response', columns: ['response_status'])]
#[ORM\Index(name: 'idx_appointment_participant_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_appointment_participant_updated_by', columns: ['updated_by_id'])]
class AppointmentParticipant
{
    use TimestampableUserTrait;

    public const ROLE_CHOICES = [
        'Organisateur' => 'organizer',
        'Participant' => 'participant',
        'Observateur' => 'observer',
        'Responsable' => 'owner',
    ];

    public const ROLE_LABELS = [
        'organizer' => 'Organisateur',
        'participant' => 'Participant',
        'observer' => 'Observateur',
        'owner' => 'Responsable',
    ];

    public const RESPONSE_CHOICES = [
        'Invite' => 'invited',
        'Accepte' => 'accepted',
        'Refusé' => 'declined',
        'En attente' => 'pending',
    ];

    public const RESPONSE_LABELS = [
        'invited' => 'Invite',
        'accepted' => 'Accepte',
        'declined' => 'Refusé',
        'pending' => 'En attente',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Appointment::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Appointment $appointment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 30)]
    private string $roleInAppointment = 'participant';

    #[ORM\Column(length: 30)]
    private string $responseStatus = 'invited';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isRequired = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }
    public function getAppointment(): ?Appointment { return $this->appointment; }
    public function setAppointment(?Appointment $appointment): static { $this->appointment = $appointment; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getRoleInAppointment(): string { return $this->roleInAppointment; }
    public function setRoleInAppointment(string $roleInAppointment): static { $this->roleInAppointment = $roleInAppointment; return $this; }
    public function getResponseStatus(): string { return $this->responseStatus; }
    public function setResponseStatus(string $responseStatus): static { $this->responseStatus = $responseStatus; return $this; }
    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }
    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): static { $this->notifiedAt = $notifiedAt; return $this; }
    public function getReminderSentAt(): ?\DateTimeImmutable { return $this->reminderSentAt; }
    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): static { $this->reminderSentAt = $reminderSentAt; return $this; }
    public function isRequired(): bool { return $this->isRequired; }
    public function setIsRequired(bool $isRequired): static { $this->isRequired = $isRequired; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getRoleLabel(): string { return self::ROLE_LABELS[$this->roleInAppointment] ?? $this->roleInAppointment; }
    public function getResponseLabel(): string { return self::RESPONSE_LABELS[$this->responseStatus] ?? $this->responseStatus; }
}
