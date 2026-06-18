<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryCampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryCampaignRepository::class)]
#[ORM\Table(name: 'inventory_campaign')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_campaign_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_inventory_campaign_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inventory_campaign_site', columns: ['site_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_responsible', columns: ['responsible_user_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_campaign_updated_by', columns: ['updated_by_id'])]
class InventoryCampaign
{
    use TimestampableUserTrait;

    public const STATUSES = [
        'Brouillon' => 'draft',
        'En cours' => 'running',
        'A valider' => 'review',
        'Validee' => 'validated',
        'Archivee' => 'archived',
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
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: InventorySite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventorySite $site = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 30)]
    private string $status = 'draft';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsibleUser = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $participants = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, InventoryCampaignLine> */
    #[ORM\OneToMany(targetEntity: InventoryCampaignLine::class, mappedBy: 'campaign', orphanRemoval: true, cascade: ['persist'])]
    private Collection $lines;

    public function __construct()
    {
        $this->startDate = new \DateTimeImmutable('today');
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = trim($reference); return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getSite(): ?InventorySite { return $this->site; }
    public function setSite(?InventorySite $site): static { $this->site = $site; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $startDate): static { $this->startDate = $startDate; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $endDate): static { $this->endDate = $endDate; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getResponsibleUser(): ?User { return $this->responsibleUser; }
    public function setResponsibleUser(?User $responsibleUser): static { $this->responsibleUser = $responsibleUser; return $this; }
    /** @return list<string> */
    public function getParticipants(): array { return $this->participants; }
    /** @param list<string>|string|null $participants */
    public function setParticipants(array|string|null $participants): static { if (is_string($participants)) { $participants = preg_split('/\r\n|\r|\n|,/', $participants) ?: []; } $this->participants = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $participants ?? []))); return $this; }
    public function getParticipantsText(): string { return implode("\n", $this->participants); }
    public function setParticipantsText(?string $participants): static { return $this->setParticipants($participants); }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    /** @return Collection<int, InventoryCampaignLine> */
    public function getLines(): Collection { return $this->lines; }
    public function addLine(InventoryCampaignLine $line): static { if (!$this->lines->contains($line)) { $this->lines->add($line); $line->setCampaign($this); } return $this; }
    public function removeLine(InventoryCampaignLine $line): static { if ($this->lines->removeElement($line) && $line->getCampaign() === $this) { $line->setCampaign(null); } return $this; }
    public function getStatusLabel(): string { return array_flip(self::STATUSES)[$this->status] ?? $this->status; }
    public function countDiscrepancies(): int { return count(array_filter($this->lines->toArray(), static fn (InventoryCampaignLine $line): bool => $line->hasDiscrepancy())); }
    public function getDiscrepancyCount(): int { return $this->countDiscrepancies(); }
}
