<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterventionIntervenantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterventionIntervenantRepository::class)]
#[ORM\Table(name: 'intervention_intervenant')]
#[ORM\UniqueConstraint(name: 'uniq_intervention_intervenant', fields: ['intervention', 'intervenant'])]
#[ORM\Index(name: 'idx_intervention_intervenant_intervention', columns: ['intervention_id'])]
#[ORM\Index(name: 'idx_intervention_intervenant_intervenant', columns: ['intervenant_id'])]
#[ORM\Index(name: 'idx_intervention_intervenant_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_intervention_intervenant_updated_by', columns: ['updated_by_id'])]
class InterventionIntervenant
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Intervention::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Intervention $intervention = null;

    #[ORM\ManyToOne(targetEntity: Intervenant::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Intervenant $intervenant = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $roleOnIntervention = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isMainIntervenant = false;

    public function __construct()
    {
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getIntervention(): ?Intervention { return $this->intervention; }
    public function setIntervention(?Intervention $intervention): static { $this->intervention = $intervention; return $this; }
    public function getIntervenant(): ?Intervenant { return $this->intervenant; }
    public function setIntervenant(?Intervenant $intervenant): static { $this->intervenant = $intervenant; return $this; }
    public function getRoleOnIntervention(): ?string { return $this->roleOnIntervention; }
    public function setRoleOnIntervention(?string $roleOnIntervention): static { $roleOnIntervention = trim((string) $roleOnIntervention); $this->roleOnIntervention = $roleOnIntervention !== '' ? $roleOnIntervention : null; return $this; }
    public function getAssignedAt(): ?\DateTimeImmutable { return $this->assignedAt; }
    public function setAssignedAt(\DateTimeImmutable $assignedAt): static { $this->assignedAt = $assignedAt; return $this; }
    public function isMainIntervenant(): bool { return $this->isMainIntervenant; }
    public function setIsMainIntervenant(bool $isMainIntervenant): static { $this->isMainIntervenant = $isMainIntervenant; return $this; }
}
