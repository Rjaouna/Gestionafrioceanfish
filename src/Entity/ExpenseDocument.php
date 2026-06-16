<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\ExpenseDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpenseDocumentRepository::class)]
#[ORM\Index(name: 'idx_expense_document_expense', columns: ['expense_id'])]
#[ORM\Index(name: 'idx_expense_document_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_expense_document_updated_by', columns: ['updated_by_id'])]
class ExpenseDocument
{
    use TimestampableUserTrait;

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_PROOF = 'proof';
    public const TYPE_OTHER = 'other';

    public const DOCUMENT_TYPES = [
        'Facture' => self::TYPE_INVOICE,
        'Reçu' => self::TYPE_RECEIPT,
        'Justificatif' => self::TYPE_PROOF,
        'Autre' => self::TYPE_OTHER,
    ];

    public const DOCUMENT_TYPE_LABELS = [
        self::TYPE_INVOICE => 'Facture',
        self::TYPE_RECEIPT => 'Reçu',
        self::TYPE_PROOF => 'Justificatif',
        self::TYPE_OTHER => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Expense $expense = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFileName = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 160)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(length: 40)]
    private string $documentType = self::TYPE_INVOICE;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): static
    {
        $this->expense = $expense;

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

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

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

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = isset(self::DOCUMENT_TYPE_LABELS[$documentType]) ? $documentType : self::TYPE_OTHER;

        return $this;
    }

    public function getDocumentTypeLabel(): string
    {
        return self::DOCUMENT_TYPE_LABELS[$this->documentType] ?? 'Autre';
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
}
