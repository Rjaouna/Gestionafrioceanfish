<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\PasswordShareRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordShareRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_password_share_user', fields: ['passwordEntry', 'user'])]
class PasswordShare
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PasswordEntry::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PasswordEntry $passwordEntry = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'passwordShares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $canView = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $canEditPassword = false;

    public function getId(): ?int
    {
        return $this->id;
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
            $this->canEditPassword = false;
        }

        return $this;
    }

    public function canEditPassword(): bool
    {
        return $this->canEditPassword;
    }

    public function setCanEditPassword(bool $canEditPassword): static
    {
        $this->canEditPassword = $canEditPassword;
        if ($canEditPassword) {
            $this->canView = true;
        }

        return $this;
    }
}
