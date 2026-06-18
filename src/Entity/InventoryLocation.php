<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryLocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryLocationRepository::class)]
#[ORM\Table(name: 'inventory_location')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_location_site_name', fields: ['site', 'name'])]
#[ORM\Index(name: 'idx_inventory_location_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_inventory_location_site', columns: ['site_id'])]
#[ORM\Index(name: 'idx_inventory_location_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_location_updated_by', columns: ['updated_by_id'])]
class InventoryLocation
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventorySite::class, inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventorySite $site = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, InventoryItem> */
    #[ORM\OneToMany(targetEntity: InventoryItem::class, mappedBy: 'location')]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSite(): ?InventorySite { return $this->site; }
    public function setSite(?InventorySite $site): static { $this->site = $site; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getDisplayName(): string { return trim(($this->site?->getName() ?? '').' / '.($this->name ?? ''), ' /'); }
    /** @return Collection<int, InventoryItem> */
    public function getItems(): Collection { return $this->items; }
}
