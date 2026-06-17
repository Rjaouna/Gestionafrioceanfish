<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\UniqueConstraint(name: 'uniq_document_internal_reference', fields: ['internalReference'])]
#[ORM\Index(name: 'idx_document_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_document_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_document_category', columns: ['category'])]
#[ORM\Index(name: 'idx_document_status', columns: ['status'])]
#[UniqueEntity(fields: ['internalReference'], message: 'Cette référence interne est déjà utilisée.')]
class Document
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const STATUS_ACTIVE = 'actif';
    public const STATUS_ARCHIVED = 'archivé';
    public const STATUS_EXPIRED = 'expiré';
    public const STATUS_TO_REVIEW = 'à vérifier';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_ARCHIVED => 'Archivé',
        self::STATUS_EXPIRED => 'Expiré',
        self::STATUS_TO_REVIEW => 'À vérifier',
    ];

    public const STATUS_BADGES = [
        self::STATUS_ACTIVE => 'text-bg-success',
        self::STATUS_ARCHIVED => 'text-bg-secondary',
        self::STATUS_EXPIRED => 'text-bg-danger',
        self::STATUS_TO_REVIEW => 'text-bg-warning',
    ];

    public const CONFIDENTIALITY_NORMAL = 'normal';
    public const CONFIDENTIALITY_CONFIDENTIAL = 'confidentiel';
    public const CONFIDENTIALITY_SENSITIVE = 'sensible';

    public const CONFIDENTIALITY_LABELS = [
        self::CONFIDENTIALITY_NORMAL => 'Normal',
        self::CONFIDENTIALITY_CONFIDENTIAL => 'Confidentiel',
        self::CONFIDENTIALITY_SENSITIVE => 'Sensible',
    ];

    public const CONFIDENTIALITY_BADGES = [
        self::CONFIDENTIALITY_NORMAL => 'text-bg-light border',
        self::CONFIDENTIALITY_CONFIDENTIAL => 'text-bg-warning',
        self::CONFIDENTIALITY_SENSITIVE => 'text-bg-danger',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFileName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $category = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $internalReference = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $documentDate = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $issuer = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $language = null;

    #[ORM\Column(length: 40, options: ['default' => self::STATUS_ACTIVE])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tags = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $confidentialityLevel = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $version = null;

    #[ORM\Column(length: 160)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, DocumentShare> */
    #[ORM\OneToMany(targetEntity: DocumentShare::class, mappedBy: 'document', orphanRemoval: true, cascade: ['persist'])]
    private Collection $shares;

    public function __construct()
    {
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getOriginalFileName(): ?string
    {
        return $this->originalFileName;
    }

    public function setOriginalFileName(string $originalFileName): static
    {
        $this->originalFileName = trim($originalFileName);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description ? trim($description) : null;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $category = trim((string) $category);
        $this->category = $category !== '' ? $category : null;

        return $this;
    }

    public function getInternalReference(): ?string
    {
        return $this->internalReference;
    }

    public function setInternalReference(?string $internalReference): static
    {
        $internalReference = trim((string) $internalReference);
        $this->internalReference = $internalReference !== '' ? $internalReference : null;

        return $this;
    }

    public function getDocumentDate(): ?\DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?\DateTimeImmutable $documentDate): static
    {
        $this->documentDate = $documentDate;

        return $this;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(?string $issuer): static
    {
        $issuer = trim((string) $issuer);
        $this->issuer = $issuer !== '' ? $issuer : null;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $language = trim((string) $language);
        $this->language = $language !== '' ? $language : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Statut de document invalide.');
        }

        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClass(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'text-bg-light border';
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(?string $tags): static
    {
        $tags = trim((string) $tags);
        $this->tags = $tags !== '' ? $tags : null;

        return $this;
    }

    /** @return list<string> */
    public function getTagList(): array
    {
        if ($this->tags === null || trim($this->tags) === '') {
            return [];
        }

        $tags = preg_split('/[,;\n]+/', $this->tags) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            $tags,
        ))));
    }

    public function getConfidentialityLevel(): ?string
    {
        return $this->confidentialityLevel;
    }

    public function setConfidentialityLevel(?string $confidentialityLevel): static
    {
        $confidentialityLevel = trim((string) $confidentialityLevel);
        if ($confidentialityLevel !== '' && !isset(self::CONFIDENTIALITY_LABELS[$confidentialityLevel])) {
            throw new \InvalidArgumentException('Niveau de confidentialité invalide.');
        }

        $this->confidentialityLevel = $confidentialityLevel !== '' ? $confidentialityLevel : null;

        return $this;
    }

    public function getConfidentialityLabel(): ?string
    {
        if ($this->confidentialityLevel === null) {
            return null;
        }

        return self::CONFIDENTIALITY_LABELS[$this->confidentialityLevel] ?? $this->confidentialityLevel;
    }

    public function getConfidentialityBadgeClass(): string
    {
        return self::CONFIDENTIALITY_BADGES[$this->confidentialityLevel] ?? 'text-bg-light border';
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $version = trim((string) $version);
        $this->version = $version !== '' ? $version : null;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

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

    /** @return Collection<int, DocumentShare> */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(DocumentShare $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setDocument($this);
        }

        return $this;
    }

    public function removeShare(DocumentShare $share): static
    {
        if ($this->shares->removeElement($share) && $share->getDocument() === $this) {
            $share->setDocument(null);
        }

        return $this;
    }
}
