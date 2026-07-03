<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimWorkerDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterimWorkerDocumentRepository::class)]
#[ORM\Table(name: 'interim_worker_document')]
#[ORM\Index(name: 'idx_interim_worker_document_worker', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_interim_worker_document_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_worker_document_updated_by', columns: ['updated_by_id'])]
class InterimWorkerDocument
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InterimWorker::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InterimWorker $worker = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFileName = null;

    #[ORM\Column(length: 160)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $fileSize = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorker(): ?InterimWorker
    {
        return $this->worker;
    }

    public function setWorker(?InterimWorker $worker): static
    {
        $this->worker = $worker;

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

    public function getFileSizeLabel(): string
    {
        if ($this->fileSize >= 1048576) {
            return number_format($this->fileSize / 1048576, 1, ',', ' ').' Mo';
        }

        return number_format(max(1, $this->fileSize / 1024), 1, ',', ' ').' Ko';
    }
}
