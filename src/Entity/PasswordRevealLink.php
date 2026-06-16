<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\PasswordRevealLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordRevealLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_password_reveal_token_hash', fields: ['tokenHash'])]
class PasswordRevealLink
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $tokenHash = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\ManyToOne(targetEntity: PasswordEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PasswordEntry $passwordEntry = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getPasswordEntry(): ?PasswordEntry
    {
        return $this->passwordEntry;
    }

    public function setPasswordEntry(?PasswordEntry $passwordEntry): static
    {
        $this->passwordEntry = $passwordEntry;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $expiresAt = $this->expiresAt;

        return !$expiresAt instanceof \DateTimeImmutable || $expiresAt <= ($now ?? new \DateTimeImmutable());
    }
}
