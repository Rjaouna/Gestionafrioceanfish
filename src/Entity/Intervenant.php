<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\IntervenantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IntervenantRepository::class)]
#[ORM\Table(name: 'intervenant')]
#[ORM\Index(name: 'idx_intervenant_type', columns: ['type'])]
#[ORM\Index(name: 'idx_intervenant_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_intervenant_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_intervenant_updated_by', columns: ['updated_by_id'])]
class Intervenant
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const TYPES = [
        'Interne' => 'interne',
        'Externe' => 'externe',
        'Prestataire' => 'prestataire',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstname = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastname = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $companyName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $phone = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    private string $type = 'prestataire';

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $speciality = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, InterventionIntervenant> */
    #[ORM\OneToMany(targetEntity: InterventionIntervenant::class, mappedBy: 'intervenant', orphanRemoval: true)]
    private Collection $assignments;

    public function __construct()
    {
        $this->assignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = trim($firstname);

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = trim($lastname);

        return $this;
    }

    public function getDisplayName(): string
    {
        return trim(sprintf('%s %s', $this->firstname ?? '', $this->lastname ?? ''));
    }

    public function getDisplayLabel(): string
    {
        $displayName = $this->getDisplayName();

        return $this->companyName !== null && $this->companyName !== ''
            ? trim($this->companyName.($displayName !== '' ? ' - '.$displayName : ''))
            : $displayName;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $companyName = trim((string) $companyName);
        $this->companyName = $companyName !== '' ? $companyName : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = trim((string) $email);
        $this->email = $email !== '' ? mb_strtolower($email) : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $phone = trim((string) $phone);
        $this->phone = $phone !== '' ? $phone : null;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSpeciality(): ?string
    {
        return $this->speciality;
    }

    public function setSpeciality(?string $speciality): static
    {
        $speciality = trim((string) $speciality);
        $this->speciality = $speciality !== '' ? $speciality : null;

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

    /** @return Collection<int, InterventionIntervenant> */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }
}
