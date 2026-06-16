<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterventionHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterventionHistoryRepository::class)]
#[ORM\Table(name: 'intervention_history')]
#[ORM\Index(name: 'idx_intervention_history_intervention', columns: ['intervention_id'])]
#[ORM\Index(name: 'idx_intervention_history_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_intervention_history_updated_by', columns: ['updated_by_id'])]
class InterventionHistory
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'histories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Intervention $intervention = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $action = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $newStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int { return $this->id; }
    public function getIntervention(): ?Intervention { return $this->intervention; }
    public function setIntervention(?Intervention $intervention): static { $this->intervention = $intervention; return $this; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): static { $this->action = trim($action); return $this; }
    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function setOldStatus(?string $oldStatus): static { $this->oldStatus = $oldStatus; return $this; }
    public function getNewStatus(): ?string { return $this->newStatus; }
    public function setNewStatus(?string $newStatus): static { $this->newStatus = $newStatus; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $comment = trim((string) $comment); $this->comment = $comment !== '' ? $comment : null; return $this; }
}
