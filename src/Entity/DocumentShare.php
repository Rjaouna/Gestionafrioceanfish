<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\DocumentShareRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentShareRepository::class)]
#[ORM\Table(name: 'document_share')]
#[ORM\UniqueConstraint(name: 'uniq_document_share_user', fields: ['document', 'user'])]
#[ORM\Index(name: 'idx_document_share_document', columns: ['document_id'])]
#[ORM\Index(name: 'idx_document_share_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_document_share_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_document_share_updated_by', columns: ['updated_by_id'])]
class DocumentShare
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $canView = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $canDownload = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function canView(): bool
    {
        return $this->canView;
    }

    public function setCanView(bool $canView): static
    {
        $this->canView = $canView;
        if (!$canView) {
            $this->canDownload = false;
        }

        return $this;
    }

    public function canDownload(): bool
    {
        return $this->canDownload;
    }

    public function setCanDownload(bool $canDownload): static
    {
        $this->canDownload = $canDownload;
        if ($canDownload) {
            $this->canView = true;
        }

        return $this;
    }

    public function getEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->emailSentAt;
    }

    public function setEmailSentAt(?\DateTimeImmutable $emailSentAt): static
    {
        $this->emailSentAt = $emailSentAt;

        return $this;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt instanceof \DateTimeImmutable
            && $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }
}
