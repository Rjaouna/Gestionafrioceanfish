<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryItemRepository::class)]
#[ORM\Table(name: 'inventory_item')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_item_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_inventory_item_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inventory_item_logistics_status', columns: ['logistics_status'])]
#[ORM\Index(name: 'idx_inventory_item_condition', columns: ['item_condition'])]
#[ORM\Index(name: 'idx_inventory_item_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_inventory_item_site', columns: ['site_id'])]
#[ORM\Index(name: 'idx_inventory_item_location', columns: ['location_id'])]
#[ORM\Index(name: 'idx_inventory_item_responsible', columns: ['responsible_user_id'])]
#[ORM\Index(name: 'idx_inventory_item_active_deleted', columns: ['is_active', 'is_deleted'])]
#[ORM\Index(name: 'idx_inventory_item_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_item_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_inventory_item_deleted_by', columns: ['deleted_by_id'])]
class InventoryItem
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const OWNERSHIP_TYPES = [
        'Entreprise' => 'company',
        'Location' => 'rental',
        'Client' => 'customer',
        'Prestataire' => 'provider',
        'Autre' => 'other',
    ];

    public const CONDITIONS = [
        'Neuf' => 'new',
        'Bon' => 'good',
        'Moyen' => 'fair',
        'À réparer' => 'repair',
        'Hors service' => 'out_of_order',
    ];

    public const STATUSES = [
        'Disponible' => 'available',
        'Affecté' => 'assigned',
        'En maintenance' => 'maintenance',
        'Réservé' => 'reserved',
        'Perdu' => 'lost',
        'Sorti du parc' => 'retired',
    ];

    public const LOGISTICS_STATUSES = [
        'Resté dans l ancienne usine' => 'legacy_remaining',
        'Transféré vers la nouvelle usine' => 'transferred_new',
        'Trouvé dans la nouvelle usine' => 'found_new',
    ];

    public const LOGISTICS_COLORS = [
        'legacy_remaining' => '#dc3545',
        'transferred_new' => '#0d6efd',
        'found_new' => '#198754',
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

    #[ORM\ManyToOne(targetEntity: InventoryCategory::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryCategory $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $dimensions = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $color = null;

    #[ORM\Column(length: 30)]
    private string $ownershipType = 'company';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $ownerName = null;

    #[ORM\ManyToOne(targetEntity: InventorySite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventorySite $site = null;

    #[ORM\ManyToOne(targetEntity: InventoryLocation::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InventoryLocation $location = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $quantity = 1;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $availableQuantity = 1;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    private string $unit = 'piece';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $serialNumber = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $brand = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $model = null;

    #[ORM\Column(name: 'item_condition', length: 30)]
    private string $condition = 'good';

    #[ORM\Column(length: 30)]
    private string $status = 'available';

    #[ORM\Column(length: 30, options: ['default' => 'legacy_remaining'])]
    private string $logisticsStatus = 'legacy_remaining';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acquisitionDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $entryDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $acquisitionValue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsibleUser = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, InventoryAttachment> */
    #[ORM\OneToMany(targetEntity: InventoryAttachment::class, mappedBy: 'item', orphanRemoval: true, cascade: ['persist'])]
    private Collection $attachments;

    /** @var Collection<int, InventoryMovement> */
    #[ORM\OneToMany(targetEntity: InventoryMovement::class, mappedBy: 'item')]
    private Collection $movements;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->movements = new ArrayCollection();
        $this->entryDate = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = trim($reference); return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getCategory(): ?InventoryCategory { return $this->category; }
    public function setCategory(?InventoryCategory $category): static { $this->category = $category; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $description = trim((string) $description); $this->description = $description !== '' ? $description : null; return $this; }
    public function getDimensions(): ?string { return $this->dimensions; }
    public function setDimensions(?string $dimensions): static { $dimensions = trim((string) $dimensions); $this->dimensions = $dimensions !== '' ? $dimensions : null; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $color = trim((string) $color); $this->color = $color !== '' ? $color : null; return $this; }
    public function getOwnershipType(): string { return $this->ownershipType; }
    public function setOwnershipType(string $ownershipType): static { $this->ownershipType = $ownershipType; return $this; }
    public function getOwnerName(): ?string { return $this->ownerName; }
    public function setOwnerName(?string $ownerName): static { $ownerName = trim((string) $ownerName); $this->ownerName = $ownerName !== '' ? $ownerName : null; return $this; }
    public function getSite(): ?InventorySite { return $this->site; }
    public function setSite(?InventorySite $site): static { $this->site = $site; return $this; }
    public function getLocation(): ?InventoryLocation { return $this->location; }
    public function setLocation(?InventoryLocation $location): static { $this->location = $location; if ($location?->getSite() !== null) { $this->site = $location->getSite(); } return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(0, $quantity); if ($this->availableQuantity > $this->quantity) { $this->availableQuantity = $this->quantity; } return $this; }
    public function getAvailableQuantity(): int { return $this->availableQuantity; }
    public function setAvailableQuantity(int $availableQuantity): static { $this->availableQuantity = max(0, min($availableQuantity, $this->quantity)); return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $unit): static { $unit = trim($unit); $this->unit = $unit !== '' ? $unit : 'piece'; return $this; }
    public function getSerialNumber(): ?string { return $this->serialNumber; }
    public function setSerialNumber(?string $serialNumber): static { $serialNumber = trim((string) $serialNumber); $this->serialNumber = $serialNumber !== '' ? $serialNumber : null; return $this; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $brand): static { $brand = trim((string) $brand); $this->brand = $brand !== '' ? $brand : null; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): static { $model = trim((string) $model); $this->model = $model !== '' ? $model : null; return $this; }
    public function getCondition(): string { return $this->condition; }
    public function setCondition(string $condition): static { $this->condition = $condition; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getLogisticsStatus(): string { return $this->logisticsStatus; }
    public function setLogisticsStatus(string $logisticsStatus): static
    {
        if (!in_array($logisticsStatus, self::LOGISTICS_STATUSES, true)) {
            throw new \DomainException('État logistique invalide.');
        }
        $this->logisticsStatus = $logisticsStatus;

        return $this;
    }
    public function getAcquisitionDate(): ?\DateTimeImmutable { return $this->acquisitionDate; }
    public function setAcquisitionDate(?\DateTimeImmutable $acquisitionDate): static { $this->acquisitionDate = $acquisitionDate; return $this; }
    public function getEntryDate(): ?\DateTimeImmutable { return $this->entryDate; }
    public function setEntryDate(?\DateTimeImmutable $entryDate): static { $this->entryDate = $entryDate; return $this; }
    public function getAcquisitionValue(): ?string { return $this->acquisitionValue; }
    public function setAcquisitionValue(null|float|string $acquisitionValue): static { $this->acquisitionValue = $acquisitionValue !== null && $acquisitionValue !== '' ? (string) $acquisitionValue : null; return $this; }
    public function getResponsibleUser(): ?User { return $this->responsibleUser; }
    public function setResponsibleUser(?User $responsibleUser): static { $this->responsibleUser = $responsibleUser; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    /** @return Collection<int, InventoryAttachment> */
    public function getAttachments(): Collection { return $this->attachments; }
    public function addAttachment(InventoryAttachment $attachment): static { if (!$this->attachments->contains($attachment)) { $this->attachments->add($attachment); $attachment->setItem($this); } return $this; }
    public function removeAttachment(InventoryAttachment $attachment): static { if ($this->attachments->removeElement($attachment) && $attachment->getItem() === $this) { $attachment->setItem(null); } return $this; }
    /** @return Collection<int, InventoryMovement> */
    public function getMovements(): Collection { return $this->movements; }
    public function getStatusLabel(): string { return array_flip(self::STATUSES)[$this->status] ?? $this->status; }
    public function getConditionLabel(): string { return array_flip(self::CONDITIONS)[$this->condition] ?? $this->condition; }
    public function getLogisticsStatusLabel(): string { return array_flip(self::LOGISTICS_STATUSES)[$this->logisticsStatus] ?? $this->logisticsStatus; }
    public function getLogisticsColor(): string { return self::LOGISTICS_COLORS[$this->logisticsStatus] ?? '#6c757d'; }
}
