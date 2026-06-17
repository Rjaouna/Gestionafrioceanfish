<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\AppointmentHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentHistoryRepository::class)]
#[ORM\Table(name: 'appointment_history')]
#[ORM\Index(name: 'idx_appointment_history_appointment', columns: ['appointment_id'])]
#[ORM\Index(name: 'idx_appointment_history_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_appointment_history_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_appointment_history_updated_by', columns: ['updated_by_id'])]
class AppointmentHistory
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Appointment::class, inversedBy: 'histories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Appointment $appointment = null;

    #[ORM\Column(length: 120)]
    private string $action = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int { return $this->id; }
    public function getAppointment(): ?Appointment { return $this->appointment; }
    public function setAppointment(?Appointment $appointment): static { $this->appointment = $appointment; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = trim($action); return $this; }
    public function getOldValue(): ?string { return $this->oldValue; }
    public function setOldValue(?string $oldValue): static { $oldValue = trim((string) $oldValue); $this->oldValue = $oldValue !== '' ? $oldValue : null; return $this; }
    public function getNewValue(): ?string { return $this->newValue; }
    public function setNewValue(?string $newValue): static { $newValue = trim((string) $newValue); $this->newValue = $newValue !== '' ? $newValue : null; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $comment = trim((string) $comment); $this->comment = $comment !== '' ? $comment : null; return $this; }
}
