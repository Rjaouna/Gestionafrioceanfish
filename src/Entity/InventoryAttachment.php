<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InventoryAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryAttachmentRepository::class)]
#[ORM\Table(name: 'inventory_attachment')]
#[ORM\Index(name: 'idx_inventory_attachment_item', columns: ['item_id'])]
#[ORM\Index(name: 'idx_inventory_attachment_type', columns: ['attachment_type'])]
#[ORM\Index(name: 'idx_inventory_attachment_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_inventory_attachment_updated_by', columns: ['updated_by_id'])]
class InventoryAttachment
{
    use TimestampableUserTrait;

    public const TYPES = [
        'Photo' => 'photo',
        'Facture' => 'invoice',
        'Garantie' => 'warranty',
        'Document' => 'document',
        'Autre' => 'other',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InventoryItem $item = null;

    #[ORM\Column(name: 'attachment_type', length: 30)]
    private string $attachmentType = 'document';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $originalFileName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $fileName = null;

    #[ORM\Column(length: 120)]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }
    public function getItem(): ?InventoryItem { return $this->item; }
    public function setItem(?InventoryItem $item): static { $this->item = $item; return $this; }
    public function getAttachmentType(): string { return $this->attachmentType; }
    public function setAttachmentType(string $attachmentType): static { $this->attachmentType = $attachmentType; return $this; }
    public function getOriginalFileName(): ?string { return $this->originalFileName; }
    public function setOriginalFileName(string $originalFileName): static { $this->originalFileName = trim($originalFileName); return $this; }
    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(string $fileName): static { $this->fileName = basename($fileName); return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $fileSize): static { $this->fileSize = max(0, $fileSize); return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function isImage(): bool { return str_starts_with($this->mimeType, 'image/'); }
    public function getTypeLabel(): string { return array_flip(self::TYPES)[$this->attachmentType] ?? $this->attachmentType; }
}
