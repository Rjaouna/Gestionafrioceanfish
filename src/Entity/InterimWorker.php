<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\InterimWorkerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterimWorkerRepository::class)]
#[ORM\Table(name: 'interim_worker')]
#[ORM\UniqueConstraint(name: 'uniq_interim_worker_registration_number', fields: ['registrationNumber'])]
#[ORM\Index(name: 'idx_interim_worker_position', columns: ['position'])]
#[ORM\Index(name: 'idx_interim_worker_type', columns: ['worker_type'])]
#[ORM\Index(name: 'idx_interim_worker_status', columns: ['status'])]
#[ORM\Index(name: 'idx_interim_worker_family_situation', columns: ['family_situation'])]
#[ORM\Index(name: 'idx_interim_worker_hire_date', columns: ['hire_date'])]
#[ORM\Index(name: 'idx_interim_worker_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_interim_worker_updated_by', columns: ['updated_by_id'])]
#[UniqueEntity(fields: ['registrationNumber'], message: 'Ce matricule est deja utilise.')]
class InterimWorker
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const FAMILY_SINGLE = 'celibataire';
    public const FAMILY_MARRIED = 'marie';
    public const FAMILY_DIVORCED = 'divorce';
    public const FAMILY_WIDOWED = 'veuf';

    public const FAMILY_LABELS = [
        self::FAMILY_SINGLE => 'Célibataire',
        self::FAMILY_MARRIED => 'Marié(e)',
        self::FAMILY_DIVORCED => 'Divorcé(e)',
        self::FAMILY_WIDOWED => 'Veuf(ve)',
    ];

    public const TYPE_OTHER = 'autre';
    public const TYPE_STUDENT = 'etudiant';

    public const TYPE_LABELS = [
        self::TYPE_OTHER => 'Autre',
        self::TYPE_STUDENT => 'Etudiant(e)',
    ];

    public const TYPE_BADGES = [
        self::TYPE_OTHER => 'text-bg-light border',
        self::TYPE_STUDENT => 'text-bg-primary',
    ];

    public const STATUS_ACTIVE = 'actif';
    public const STATUS_INACTIVE = 'inactif';
    public const STATUS_PENDING = 'en_attente';
    public const STATUS_ENDED = 'fin_mission';
    public const STATUS_DO_NOT_RECALL = 'a_ne_pas_rappeler';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Actif',
        self::STATUS_INACTIVE => 'Inactif',
        self::STATUS_PENDING => 'En attente',
        self::STATUS_ENDED => 'Fin de mission',
        self::STATUS_DO_NOT_RECALL => 'A ne pas rappeler',
    ];

    public const STATUS_BADGES = [
        self::STATUS_ACTIVE => 'text-bg-success',
        self::STATUS_INACTIVE => 'text-bg-secondary',
        self::STATUS_PENDING => 'text-bg-warning',
        self::STATUS_ENDED => 'text-bg-info',
        self::STATUS_DO_NOT_RECALL => 'text-bg-danger',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $lastName = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $position = null;

    #[ORM\Column(length: 30, options: ['default' => self::TYPE_OTHER])]
    #[Assert\NotBlank]
    private string $workerType = self::TYPE_OTHER;

    #[ORM\Column(length: 50)]
    #[Assert\Length(max: 50)]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^(?:0[67]\d{8}|\+212[67]\d{8}|\+33[67]\d{8})$/',
        message: 'Le telephone doit etre au format Maroc ou France : 06..., 07..., +212... ou +33...'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('today', message: 'La date de naissance ne peut pas etre dans le futur.')]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $birthPlace = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[A-Z0-9]+$/', message: 'Le CIN doit contenir uniquement des lettres et chiffres.')]
    private ?string $cin = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $familySituation = self::FAMILY_SINGLE;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 20)]
    private int $childrenCount = 0;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: '-20 years', message: 'La date d embauche est trop ancienne.')]
    #[Assert\LessThanOrEqual(value: '+1 month', message: 'La date d embauche est trop eloignee dans le futur.')]
    private ?\DateTimeImmutable $hireDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $missionEndDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $missionEndReason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $missionEndedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $doNotRecallAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $doNotRecallReason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastStatusChangedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $tempAgency = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $managerObservations = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $employeeSignature = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $managerSignature = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $signatureDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalComment = null;

    #[ORM\Column(length: 40)]
    #[Assert\NotBlank]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoFileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoOriginalFileName = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $photoMimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $photoFileSize = null;

    /** @var Collection<int, InterimWorkerDocument> */
    #[ORM\OneToMany(targetEntity: InterimWorkerDocument::class, mappedBy: 'worker', orphanRemoval: true, cascade: ['persist'])]
    private Collection $documents;

    /** @var Collection<int, InterimWorkerAction> */
    #[ORM\OneToMany(targetEntity: InterimWorkerAction::class, mappedBy: 'worker', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['actionAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $actions;

    public function __construct()
    {
        $this->hireDate = new \DateTimeImmutable('today');
        $this->signatureDate = new \DateTimeImmutable('today');
        $this->documents = new ArrayCollection();
        $this->actions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = mb_strtoupper(trim($lastName));

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = mb_convert_case(trim($firstName), MB_CASE_TITLE, 'UTF-8');

        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->lastName ?? '').' '.($this->firstName ?? ''));
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $address = trim((string) $address);
        $this->address = $address !== '' ? $address : null;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): static
    {
        $this->position = trim($position);

        return $this;
    }

    public function getWorkerType(): string
    {
        return $this->workerType;
    }

    public function setWorkerType(string $workerType): static
    {
        if (!isset(self::TYPE_LABELS[$workerType])) {
            throw new \InvalidArgumentException('Profil interimaire invalide.');
        }

        $this->workerType = $workerType;

        return $this;
    }

    public function getWorkerTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->workerType] ?? $this->workerType;
    }

    public function getWorkerTypeBadgeClass(): string
    {
        return self::TYPE_BADGES[$this->workerType] ?? 'text-bg-light border';
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): static
    {
        $registrationNumber = mb_strtoupper(trim((string) $registrationNumber));
        $this->registrationNumber = $registrationNumber !== '' ? $registrationNumber : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $phone = trim($phone);
        $this->phone = preg_replace('/[\s().-]+/', '', $phone) ?: $phone;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getBirthPlace(): ?string
    {
        return $this->birthPlace;
    }

    public function setBirthPlace(?string $birthPlace): static
    {
        $birthPlace = trim((string) $birthPlace);
        $this->birthPlace = $birthPlace !== '' ? $birthPlace : null;

        return $this;
    }

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(?string $cin): static
    {
        $cin = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $cin)) ?? '');
        $this->cin = $cin !== '' ? $cin : null;

        return $this;
    }

    public function getFamilySituation(): string
    {
        return $this->familySituation;
    }

    public function setFamilySituation(string $familySituation): static
    {
        if (!isset(self::FAMILY_LABELS[$familySituation])) {
            throw new \InvalidArgumentException('Situation familiale invalide.');
        }

        $this->familySituation = $familySituation;

        return $this;
    }

    public function getFamilySituationLabel(): string
    {
        return self::FAMILY_LABELS[$this->familySituation] ?? $this->familySituation;
    }

    public function getChildrenCount(): int
    {
        return $this->childrenCount;
    }

    public function setChildrenCount(int $childrenCount): static
    {
        $this->childrenCount = max(0, min(20, $childrenCount));

        return $this;
    }

    public function getHireDate(): ?\DateTimeImmutable
    {
        return $this->hireDate;
    }

    public function setHireDate(\DateTimeImmutable $hireDate): static
    {
        $this->hireDate = $hireDate;

        return $this;
    }

    public function getMissionEndDate(): ?\DateTimeImmutable
    {
        return $this->missionEndDate;
    }

    public function setMissionEndDate(?\DateTimeImmutable $missionEndDate): static
    {
        $this->missionEndDate = $missionEndDate;

        return $this;
    }

    public function getMissionEndReason(): ?string
    {
        return $this->missionEndReason;
    }

    public function setMissionEndReason(?string $missionEndReason): static
    {
        $missionEndReason = trim((string) $missionEndReason);
        $this->missionEndReason = $missionEndReason !== '' ? $missionEndReason : null;

        return $this;
    }

    public function getMissionEndedAt(): ?\DateTimeImmutable
    {
        return $this->missionEndedAt;
    }

    public function setMissionEndedAt(?\DateTimeImmutable $missionEndedAt): static
    {
        $this->missionEndedAt = $missionEndedAt;

        return $this;
    }

    public function getDoNotRecallAt(): ?\DateTimeImmutable
    {
        return $this->doNotRecallAt;
    }

    public function setDoNotRecallAt(?\DateTimeImmutable $doNotRecallAt): static
    {
        $this->doNotRecallAt = $doNotRecallAt;

        return $this;
    }

    public function getDoNotRecallReason(): ?string
    {
        return $this->doNotRecallReason;
    }

    public function setDoNotRecallReason(?string $doNotRecallReason): static
    {
        $doNotRecallReason = trim((string) $doNotRecallReason);
        $this->doNotRecallReason = $doNotRecallReason !== '' ? $doNotRecallReason : null;

        return $this;
    }

    public function getLastStatusChangedAt(): ?\DateTimeImmutable
    {
        return $this->lastStatusChangedAt;
    }

    public function setLastStatusChangedAt(?\DateTimeImmutable $lastStatusChangedAt): static
    {
        $this->lastStatusChangedAt = $lastStatusChangedAt;

        return $this;
    }

    public function getTempAgency(): ?string
    {
        return $this->tempAgency;
    }

    public function setTempAgency(?string $tempAgency): static
    {
        $tempAgency = trim((string) $tempAgency);
        $this->tempAgency = $tempAgency !== '' ? $tempAgency : null;

        return $this;
    }

    public function getManagerObservations(): ?string
    {
        return $this->managerObservations;
    }

    public function setManagerObservations(?string $managerObservations): static
    {
        $managerObservations = trim((string) $managerObservations);
        $this->managerObservations = $managerObservations !== '' ? $managerObservations : null;

        return $this;
    }

    public function getEmployeeSignature(): ?string
    {
        return $this->employeeSignature;
    }

    public function setEmployeeSignature(?string $employeeSignature): static
    {
        $employeeSignature = trim((string) $employeeSignature);
        $this->employeeSignature = $employeeSignature !== '' ? $employeeSignature : null;

        return $this;
    }

    public function getManagerSignature(): ?string
    {
        return $this->managerSignature;
    }

    public function setManagerSignature(?string $managerSignature): static
    {
        $managerSignature = trim((string) $managerSignature);
        $this->managerSignature = $managerSignature !== '' ? $managerSignature : null;

        return $this;
    }

    public function getSignatureDate(): ?\DateTimeImmutable
    {
        return $this->signatureDate;
    }

    public function setSignatureDate(?\DateTimeImmutable $signatureDate): static
    {
        $this->signatureDate = $signatureDate;

        return $this;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): static
    {
        $internalComment = trim((string) $internalComment);
        $this->internalComment = $internalComment !== '' ? $internalComment : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Statut interimaire invalide.');
        }

        $this->status = $status;
        $this->isActive = !in_array($status, [self::STATUS_ENDED, self::STATUS_INACTIVE, self::STATUS_DO_NOT_RECALL], true);

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

    public function isDoNotRecall(): bool
    {
        return $this->status === self::STATUS_DO_NOT_RECALL;
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

    public function getPhotoFileName(): ?string
    {
        return $this->photoFileName;
    }

    public function setPhotoFileName(?string $photoFileName): static
    {
        $this->photoFileName = $photoFileName;

        return $this;
    }

    public function getPhotoOriginalFileName(): ?string
    {
        return $this->photoOriginalFileName;
    }

    public function setPhotoOriginalFileName(?string $photoOriginalFileName): static
    {
        $this->photoOriginalFileName = $photoOriginalFileName;

        return $this;
    }

    public function getPhotoMimeType(): ?string
    {
        return $this->photoMimeType;
    }

    public function setPhotoMimeType(?string $photoMimeType): static
    {
        $this->photoMimeType = $photoMimeType;

        return $this;
    }

    public function getPhotoFileSize(): ?int
    {
        return $this->photoFileSize;
    }

    public function setPhotoFileSize(?int $photoFileSize): static
    {
        $this->photoFileSize = $photoFileSize;

        return $this;
    }

    public function hasPhoto(): bool
    {
        return $this->photoFileName !== null && $this->photoFileName !== '';
    }

    /** @return Collection<int, InterimWorkerDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(InterimWorkerDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setWorker($this);
        }

        return $this;
    }

    public function removeDocument(InterimWorkerDocument $document): static
    {
        if ($this->documents->removeElement($document) && $document->getWorker() === $this) {
            $document->setWorker(null);
        }

        return $this;
    }

    /** @return Collection<int, InterimWorkerAction> */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(InterimWorkerAction $action): static
    {
        if (!$this->actions->contains($action)) {
            $this->actions->add($action);
            $action->setWorker($this);
        }

        return $this;
    }

    public function removeAction(InterimWorkerAction $action): static
    {
        if ($this->actions->removeElement($action) && $action->getWorker() === $this) {
            $action->setWorker(null);
        }

        return $this;
    }
}
