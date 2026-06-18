<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryCartonStockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryCartonStockRepository::class)]
#[ORM\Table(name: 'inventory_carton_stock')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_carton_stock_name', fields: ['name'])]
#[ORM\Index(name: 'idx_inventory_carton_stock_active', columns: ['is_active'])]
#[ORM\Index(name: 'idx_inventory_carton_stock_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_carton_stock_updated_by', columns: ['updated_by_id'])]
class InventoryCartonStock
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $sourceSheet = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, InventoryCartonStockLine> */
    #[ORM\OneToMany(targetEntity: InventoryCartonStockLine::class, mappedBy: 'stock', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getSourceSheet(): ?string { return $this->sourceSheet; }
    public function setSourceSheet(?string $sourceSheet): static { $sourceSheet = trim((string) $sourceSheet); $this->sourceSheet = $sourceSheet !== '' ? $sourceSheet : null; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    /** @return Collection<int, InventoryCartonStockLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(InventoryCartonStockLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setStock($this);
        }

        return $this;
    }

    public function removeLine(InventoryCartonStockLine $line): static
    {
        if ($this->lines->removeElement($line) && $line->getStock() === $this) {
            $line->setStock(null);
        }

        return $this;
    }
}
