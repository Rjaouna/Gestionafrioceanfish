<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contact')]
#[ORM\Index(name: 'idx_contact_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_contact_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_contact_type', columns: ['type'])]
#[ORM\Index(name: 'idx_contact_city', columns: ['city'])]
class Contact
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $fullName = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $type = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $contactPersonName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $contactPersonPosition = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $mobile = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $mobileSecondary = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $mobileTertiary = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $landline = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $city = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $postalAddress = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, ContactShare> */
    #[ORM\OneToMany(targetEntity: ContactShare::class, mappedBy: 'contact', orphanRemoval: true, cascade: ['persist'])]
    private Collection $shares;

    public function __construct()
    {
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = trim($type);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = trim((string) $email);
        $this->email = $email !== '' ? mb_strtolower($email) : null;

        return $this;
    }

    public function getContactPersonName(): ?string
    {
        return $this->contactPersonName;
    }

    public function setContactPersonName(?string $contactPersonName): static
    {
        $contactPersonName = trim((string) $contactPersonName);
        $this->contactPersonName = $contactPersonName !== '' ? $contactPersonName : null;

        return $this;
    }

    public function getContactPersonPosition(): ?string
    {
        return $this->contactPersonPosition;
    }

    public function setContactPersonPosition(?string $contactPersonPosition): static
    {
        $contactPersonPosition = trim((string) $contactPersonPosition);
        $this->contactPersonPosition = $contactPersonPosition !== '' ? $contactPersonPosition : null;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): static
    {
        $mobile = trim((string) $mobile);
        $this->mobile = $mobile !== '' ? $mobile : null;

        return $this;
    }

    public function getMobileSecondary(): ?string
    {
        return $this->mobileSecondary;
    }

    public function setMobileSecondary(?string $mobileSecondary): static
    {
        $mobileSecondary = trim((string) $mobileSecondary);
        $this->mobileSecondary = $mobileSecondary !== '' ? $mobileSecondary : null;

        return $this;
    }

    public function getMobileTertiary(): ?string
    {
        return $this->mobileTertiary;
    }

    public function setMobileTertiary(?string $mobileTertiary): static
    {
        $mobileTertiary = trim((string) $mobileTertiary);
        $this->mobileTertiary = $mobileTertiary !== '' ? $mobileTertiary : null;

        return $this;
    }

    /** @return list<string> */
    public function getMobileNumbers(): array
    {
        return array_values(array_filter([
            $this->mobile,
            $this->mobileSecondary,
            $this->mobileTertiary,
        ], static fn (?string $mobile): bool => $mobile !== null && $mobile !== ''));
    }

    public function getLandline(): ?string
    {
        return $this->landline;
    }

    public function setLandline(?string $landline): static
    {
        $landline = trim((string) $landline);
        $this->landline = $landline !== '' ? $landline : null;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $city = trim((string) $city);
        $this->city = $city !== '' ? $city : null;

        return $this;
    }

    public function getPostalAddress(): ?string
    {
        return $this->postalAddress;
    }

    public function setPostalAddress(?string $postalAddress): static
    {
        $postalAddress = trim((string) $postalAddress);
        $this->postalAddress = $postalAddress !== '' ? $postalAddress : null;

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

    /** @return Collection<int, ContactShare> */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(ContactShare $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setContact($this);
        }

        return $this;
    }

    public function removeShare(ContactShare $share): static
    {
        if ($this->shares->removeElement($share) && $share->getContact() === $this) {
            $share->setContact(null);
        }

        return $this;
    }
}
