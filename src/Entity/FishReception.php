<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\FishReceptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FishReceptionRepository::class)]
#[ORM\Table(name: 'fish_reception')]
#[ORM\UniqueConstraint(name: 'uniq_fish_reception_numero_reception', fields: ['numeroReception'])]
#[ORM\UniqueConstraint(name: 'uniq_fish_reception_numero_lot', fields: ['numeroLot'])]
#[ORM\Index(name: 'idx_fish_reception_date', columns: ['date_reception'])]
#[ORM\Index(name: 'idx_fish_reception_statut', columns: ['statut'])]
#[ORM\Index(name: 'idx_fish_reception_fournisseur', columns: ['fournisseur'])]
#[ORM\Index(name: 'idx_fish_reception_espece', columns: ['espece_poisson'])]
#[ORM\Index(name: 'idx_fish_reception_chambre', columns: ['chambre_froide'])]
#[ORM\Index(name: 'idx_fish_reception_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_deleted_by', columns: ['deleted_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_received_by', columns: ['received_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_treatment_by', columns: ['treatment_started_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_stored_by', columns: ['stored_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_return_storage_by', columns: ['remise_en_chambre_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_expedited_by', columns: ['expedited_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_closed_by', columns: ['closed_by_id'])]
#[ORM\Index(name: 'idx_fish_reception_blocked_by', columns: ['blocked_by_id'])]
#[UniqueEntity(fields: ['numeroReception'], message: 'Ce numero de reception existe deja.')]
#[UniqueEntity(fields: ['numeroLot'], message: 'Ce numero de lot existe deja.')]
class FishReception
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const STATUS_DRAFT = 'brouillon';
    public const STATUS_RECEIVED = 'receptionnee';
    public const STATUS_PROCESSING = 'traitement';
    public const STATUS_FROZEN = 'congelee';
    public const STATUS_STORED = 'stockee';
    public const STATUS_PACKAGED = 'emballee';
    public const STATUS_RETURNED_TO_ROOM = 'remise_chambre';
    public const STATUS_SHIPPED = 'expediee';
    public const STATUS_CLOSED = 'cloturee';
    public const STATUS_BLOCKED = 'bloquee';

    public const OPERATION_PURCHASE = 'achat_matiere';
    public const OPERATION_SERVICE = 'prestation_service';

    public const OPERATION_LABELS = [
        self::OPERATION_PURCHASE => 'Achat matière première',
        self::OPERATION_SERVICE => 'Transformation / stockage seulement',
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_RECEIVED => 'Receptionnee',
        self::STATUS_PROCESSING => 'En traitement',
        self::STATUS_FROZEN => 'Congelée',
        self::STATUS_STORED => 'En cristallisation',
        self::STATUS_PACKAGED => 'Emballée',
        self::STATUS_RETURNED_TO_ROOM => 'Remise en chambre',
        self::STATUS_SHIPPED => 'Expédiée',
        self::STATUS_CLOSED => 'Clôturée',
        self::STATUS_BLOCKED => 'Bloquee',
    ];

    public const STATUS_BADGES = [
        self::STATUS_DRAFT => 'text-bg-secondary',
        self::STATUS_RECEIVED => 'text-bg-primary',
        self::STATUS_PROCESSING => 'text-bg-info',
        self::STATUS_FROZEN => 'text-bg-info',
        self::STATUS_STORED => 'text-bg-success',
        self::STATUS_RETURNED_TO_ROOM => 'text-bg-success',
        self::STATUS_PACKAGED => 'text-bg-success',
        self::STATUS_SHIPPED => 'text-bg-dark',
        self::STATUS_CLOSED => 'text-bg-dark',
        self::STATUS_BLOCKED => 'text-bg-danger',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private ?string $numeroReception = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private ?string $numeroLot = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $dateReception = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureDebutReception = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureFinReception = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $fournisseur = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $provenance = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $matriculeVehicule = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $chauffeur = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $especePoisson = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $nomScientifique = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $presentationProduit = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $etatProduit = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $numeroBonLivraison = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteIndiqueeBl = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\Positive]
    private string $quantiteReceptionnee = '0.000';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreCaissesReception = 0;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperaturePoissonReception = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $categorieFraicheur = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $presenceGlace = true;

    #[ORM\Column(length: 40, options: ['default' => self::OPERATION_PURCHASE])]
    #[Assert\Choice(choices: [self::OPERATION_PURCHASE, self::OPERATION_SERVICE])]
    private string $operationType = self::OPERATION_PURCHASE;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionPrixAchatKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionMontantAchatTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionFraisTransport = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionFraisDechargement = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionFraisGlaceConsommables = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionFraisControleQualite = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $receptionAutresFrais = '0.00';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $receptionReferenceFacture = null;

    #[ORM\Column(length: 10, options: ['default' => 'MAD'])]
    #[Assert\Length(max: 10)]
    private string $receptionDevise = 'MAD';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDebutTraitement = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureDebutTraitement = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureEauGlacee = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreCaissesApresTraitement = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsMoyenParCaisse = '0.000';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreMoules = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreCaissesParPalette = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreTotalPalettes = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteTotalePreparee = '0.000';

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $tunnel = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateEntreeTunnel = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureEntreeTunnel = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureSortieTunnel = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureTunnel = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateSortieTunnel = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureCoeurProduit = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteCongelee = '0.000';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $chambreFroide = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureChambre = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateEntreeStockage = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureEntreeStockage = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteStockee = '0.000';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateConditionnement = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureDebutConditionnement = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureFinConditionnement = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $produitConditionne = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteConditionnee = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsNet = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsDechetsEmballage = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsPertesEmballage = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutHoraireEmballage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutEmballage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureStockage = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $chambreRemiseEnChambre = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateRemiseEnChambre = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureRemiseEnChambre = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureChambreRemise = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $temperatureProduitRemise = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteRemiseEnChambre = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteTotaleExpediee = '0.000';

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $destinationFinaleClient = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expeditionDateDepart = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $expeditionHeureDepart = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $expeditionMatriculeVehicule = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $expeditionChauffeur = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $expeditionResponsableChargement = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $expeditionTemperatureProduit = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $expeditionNumeroPlomb = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $expeditionObservations = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $observations = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $responsableProduction = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $signatureResponsable = null;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\Choice(choices: [
        self::STATUS_DRAFT,
        self::STATUS_RECEIVED,
        self::STATUS_PROCESSING,
        self::STATUS_FROZEN,
        self::STATUS_STORED,
        self::STATUS_PACKAGED,
        self::STATUS_RETURNED_TO_ROOM,
        self::STATUS_SHIPPED,
        self::STATUS_CLOSED,
        self::STATUS_BLOCKED,
    ])]
    private string $statut = self::STATUS_DRAFT;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $quantiteUtiliseeProduction = '0.000';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $treatmentStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $storedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $remiseEnChambreAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expeditedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $blockedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $blockReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $receivedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $treatmentStartedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $storedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $remiseEnChambreBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $expeditedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $blockedBy = null;

    /** @var Collection<int, FishReceptionStorageMovement> */
    #[ORM\OneToMany(targetEntity: FishReceptionStorageMovement::class, mappedBy: 'reception', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['movementDate' => 'ASC', 'movementTime' => 'ASC', 'id' => 'ASC'])]
    private Collection $storageMovements;

    /** @var Collection<int, CoutRevient> */
    #[ORM\OneToMany(targetEntity: CoutRevient::class, mappedBy: 'reception')]
    #[ORM\OrderBy(['dateProduction' => 'DESC', 'id' => 'DESC'])]
    private Collection $coutRevients;

    public function __construct()
    {
        $this->dateReception = new \DateTimeImmutable('today');
        $this->storageMovements = new ArrayCollection();
        $this->coutRevients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroReception(): ?string
    {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): static
    {
        $numeroReception = mb_strtoupper(trim((string) $numeroReception));
        $this->numeroReception = $numeroReception !== '' ? $numeroReception : null;

        return $this;
    }

    public function getNumeroLot(): ?string
    {
        return $this->numeroLot;
    }

    public function setNumeroLot(?string $numeroLot): static
    {
        $numeroLot = mb_strtoupper(trim((string) $numeroLot));
        $this->numeroLot = $numeroLot !== '' ? $numeroLot : null;

        return $this;
    }

    public function getDateReception(): ?\DateTimeImmutable
    {
        return $this->dateReception;
    }

    public function setDateReception(?\DateTimeImmutable $dateReception): static
    {
        $this->dateReception = $dateReception;

        return $this;
    }

    public function getHeureDebutReception(): ?\DateTimeImmutable
    {
        return $this->heureDebutReception;
    }

    public function setHeureDebutReception(?\DateTimeImmutable $heureDebutReception): static
    {
        $this->heureDebutReception = $heureDebutReception;

        return $this;
    }

    public function getHeureFinReception(): ?\DateTimeImmutable
    {
        return $this->heureFinReception;
    }

    public function setHeureFinReception(?\DateTimeImmutable $heureFinReception): static
    {
        $this->heureFinReception = $heureFinReception;

        return $this;
    }

    public function getFournisseur(): ?string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(string $fournisseur): static
    {
        $this->fournisseur = trim($fournisseur);

        return $this;
    }

    public function getProvenance(): ?string
    {
        return $this->provenance;
    }

    public function setProvenance(?string $provenance): static
    {
        $this->provenance = $this->nullableString($provenance);

        return $this;
    }

    public function getMatriculeVehicule(): ?string
    {
        return $this->matriculeVehicule;
    }

    public function setMatriculeVehicule(?string $matriculeVehicule): static
    {
        $this->matriculeVehicule = $this->nullableString($matriculeVehicule);

        return $this;
    }

    public function getChauffeur(): ?string
    {
        return $this->chauffeur;
    }

    public function setChauffeur(?string $chauffeur): static
    {
        $this->chauffeur = $this->nullableString($chauffeur);

        return $this;
    }

    public function getEspecePoisson(): ?string
    {
        return $this->especePoisson;
    }

    public function setEspecePoisson(string $especePoisson): static
    {
        $this->especePoisson = trim($especePoisson);

        return $this;
    }

    public function getNomScientifique(): ?string
    {
        return $this->nomScientifique;
    }

    public function setNomScientifique(?string $nomScientifique): static
    {
        $this->nomScientifique = $this->nullableString($nomScientifique);

        return $this;
    }

    public function getPresentationProduit(): ?string
    {
        return $this->presentationProduit;
    }

    public function setPresentationProduit(string $presentationProduit): static
    {
        $this->presentationProduit = trim($presentationProduit);

        return $this;
    }

    public function getEtatProduit(): ?string
    {
        return $this->etatProduit;
    }

    public function setEtatProduit(string $etatProduit): static
    {
        $this->etatProduit = trim($etatProduit);

        return $this;
    }

    public function getNumeroBonLivraison(): ?string
    {
        return $this->numeroBonLivraison;
    }

    public function setNumeroBonLivraison(?string $numeroBonLivraison): static
    {
        $this->numeroBonLivraison = $this->nullableString($numeroBonLivraison);

        return $this;
    }

    public function getQuantiteIndiqueeBl(): string
    {
        return $this->quantiteIndiqueeBl;
    }

    public function setQuantiteIndiqueeBl(int|float|string|null $quantiteIndiqueeBl): static
    {
        $this->quantiteIndiqueeBl = $this->decimal($quantiteIndiqueeBl, 3);

        return $this;
    }

    public function getQuantiteReceptionnee(): string
    {
        return $this->quantiteReceptionnee;
    }

    public function setQuantiteReceptionnee(int|float|string|null $quantiteReceptionnee): static
    {
        $this->quantiteReceptionnee = $this->decimal($quantiteReceptionnee, 3);

        return $this;
    }

    public function getNombreCaissesReception(): int
    {
        return $this->nombreCaissesReception;
    }

    public function setNombreCaissesReception(int|string|null $nombreCaissesReception): static
    {
        $this->nombreCaissesReception = max(0, (int) $nombreCaissesReception);

        return $this;
    }

    public function getTemperaturePoissonReception(): ?string
    {
        return $this->temperaturePoissonReception;
    }

    public function setTemperaturePoissonReception(int|float|string|null $temperaturePoissonReception): static
    {
        $this->temperaturePoissonReception = $this->nullableDecimal($temperaturePoissonReception);

        return $this;
    }

    public function getCategorieFraicheur(): ?string
    {
        return $this->categorieFraicheur;
    }

    public function setCategorieFraicheur(string $categorieFraicheur): static
    {
        $this->categorieFraicheur = trim($categorieFraicheur);

        return $this;
    }

    public function hasPresenceGlace(): bool
    {
        return $this->presenceGlace;
    }

    public function setPresenceGlace(bool|string|null $presenceGlace): static
    {
        $this->presenceGlace = filter_var($presenceGlace, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

        return $this;
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function setOperationType(?string $operationType): static
    {
        $operationType = (string) $operationType;
        $this->operationType = isset(self::OPERATION_LABELS[$operationType]) ? $operationType : self::OPERATION_PURCHASE;

        return $this;
    }

    public function getOperationTypeLabel(): string
    {
        return self::OPERATION_LABELS[$this->operationType] ?? $this->operationType;
    }

    public function isPurchaseOperation(): bool
    {
        return $this->operationType === self::OPERATION_PURCHASE;
    }

    public function getReceptionPrixAchatKg(): string
    {
        return $this->receptionPrixAchatKg;
    }

    public function setReceptionPrixAchatKg(int|float|string|null $receptionPrixAchatKg): static
    {
        $this->receptionPrixAchatKg = $this->decimal($receptionPrixAchatKg);

        return $this;
    }

    public function getReceptionMontantAchatTotal(): string
    {
        return $this->receptionMontantAchatTotal;
    }

    public function setReceptionMontantAchatTotal(int|float|string|null $receptionMontantAchatTotal): static
    {
        $this->receptionMontantAchatTotal = $this->decimal($receptionMontantAchatTotal);

        return $this;
    }

    public function getReceptionFraisTransport(): string
    {
        return $this->receptionFraisTransport;
    }

    public function setReceptionFraisTransport(int|float|string|null $receptionFraisTransport): static
    {
        $this->receptionFraisTransport = $this->decimal($receptionFraisTransport);

        return $this;
    }

    public function getReceptionFraisDechargement(): string
    {
        return $this->receptionFraisDechargement;
    }

    public function setReceptionFraisDechargement(int|float|string|null $receptionFraisDechargement): static
    {
        $this->receptionFraisDechargement = $this->decimal($receptionFraisDechargement);

        return $this;
    }

    public function getReceptionFraisGlaceConsommables(): string
    {
        return $this->receptionFraisGlaceConsommables;
    }

    public function setReceptionFraisGlaceConsommables(int|float|string|null $receptionFraisGlaceConsommables): static
    {
        $this->receptionFraisGlaceConsommables = $this->decimal($receptionFraisGlaceConsommables);

        return $this;
    }

    public function getReceptionFraisControleQualite(): string
    {
        return $this->receptionFraisControleQualite;
    }

    public function setReceptionFraisControleQualite(int|float|string|null $receptionFraisControleQualite): static
    {
        $this->receptionFraisControleQualite = $this->decimal($receptionFraisControleQualite);

        return $this;
    }

    public function getReceptionAutresFrais(): string
    {
        return $this->receptionAutresFrais;
    }

    public function setReceptionAutresFrais(int|float|string|null $receptionAutresFrais): static
    {
        $this->receptionAutresFrais = $this->decimal($receptionAutresFrais);

        return $this;
    }

    public function getReceptionReferenceFacture(): ?string
    {
        return $this->receptionReferenceFacture;
    }

    public function setReceptionReferenceFacture(?string $receptionReferenceFacture): static
    {
        $this->receptionReferenceFacture = $this->nullableString($receptionReferenceFacture);

        return $this;
    }

    public function getReceptionDevise(): string
    {
        return $this->receptionDevise;
    }

    public function setReceptionDevise(?string $receptionDevise): static
    {
        $receptionDevise = strtoupper(trim((string) $receptionDevise));
        $this->receptionDevise = $receptionDevise !== '' ? mb_substr($receptionDevise, 0, 10) : 'MAD';

        return $this;
    }

    public function getCoutAchatReceptionValue(): float
    {
        if (!$this->isPurchaseOperation()) {
            return 0.0;
        }

        $total = (float) $this->receptionMontantAchatTotal;
        if ($total > 0.001) {
            return $total;
        }

        return $this->getQuantiteReceptionneeValue() * (float) $this->receptionPrixAchatKg;
    }

    public function getCoutAchatKgReceptionValue(): float
    {
        $quantity = $this->getQuantiteReceptionneeValue();

        return $quantity > 0.001 ? $this->getCoutAchatReceptionValue() / $quantity : 0.0;
    }

    public function getCoutFraisReceptionValue(): float
    {
        return (float) $this->receptionFraisTransport
            + (float) $this->receptionFraisDechargement
            + (float) $this->receptionFraisGlaceConsommables
            + (float) $this->receptionFraisControleQualite
            + (float) $this->receptionAutresFrais;
    }

    public function getCoutFraisTransportKgReceptionValue(): float
    {
        $quantity = $this->getQuantiteReceptionneeValue();

        return $quantity > 0.001 ? (float) $this->receptionFraisTransport / $quantity : 0.0;
    }

    public function getCoutAutresFraisKgReceptionValue(): float
    {
        $quantity = $this->getQuantiteReceptionneeValue();
        $fees = $this->getCoutFraisReceptionValue() - (float) $this->receptionFraisTransport;

        return $quantity > 0.001 ? max(0.0, $fees) / $quantity : 0.0;
    }

    public function getCoutTotalReceptionValue(): float
    {
        return $this->getCoutAchatReceptionValue() + $this->getCoutFraisReceptionValue();
    }

    public function getCoutKgReceptionValue(): float
    {
        $quantity = $this->getQuantiteReceptionneeValue();

        return $quantity > 0.001 ? $this->getCoutTotalReceptionValue() / $quantity : 0.0;
    }

    public function getDateDebutTraitement(): ?\DateTimeImmutable
    {
        return $this->dateDebutTraitement;
    }

    public function setDateDebutTraitement(?\DateTimeImmutable $dateDebutTraitement): static
    {
        $this->dateDebutTraitement = $dateDebutTraitement;

        return $this;
    }

    public function getHeureDebutTraitement(): ?\DateTimeImmutable
    {
        return $this->heureDebutTraitement;
    }

    public function setHeureDebutTraitement(?\DateTimeImmutable $heureDebutTraitement): static
    {
        $this->heureDebutTraitement = $heureDebutTraitement;

        return $this;
    }

    public function getTemperatureEauGlacee(): ?string
    {
        return $this->temperatureEauGlacee;
    }

    public function setTemperatureEauGlacee(int|float|string|null $temperatureEauGlacee): static
    {
        $this->temperatureEauGlacee = $this->nullableDecimal($temperatureEauGlacee);

        return $this;
    }

    public function getNombreCaissesApresTraitement(): int
    {
        return $this->nombreCaissesApresTraitement;
    }

    public function setNombreCaissesApresTraitement(int|string|null $nombreCaissesApresTraitement): static
    {
        $this->nombreCaissesApresTraitement = max(0, (int) $nombreCaissesApresTraitement);

        return $this;
    }

    public function getPoidsMoyenParCaisse(): string
    {
        return $this->poidsMoyenParCaisse;
    }

    public function setPoidsMoyenParCaisse(int|float|string|null $poidsMoyenParCaisse): static
    {
        $this->poidsMoyenParCaisse = $this->decimal($poidsMoyenParCaisse, 3);

        return $this;
    }

    public function getNombreMoules(): int
    {
        return $this->nombreMoules;
    }

    public function setNombreMoules(int|string|null $nombreMoules): static
    {
        $this->nombreMoules = max(0, (int) $nombreMoules);

        return $this;
    }

    public function getNombreCaissesParPalette(): int
    {
        return $this->nombreCaissesParPalette;
    }

    public function setNombreCaissesParPalette(int|string|null $nombreCaissesParPalette): static
    {
        $this->nombreCaissesParPalette = max(0, (int) $nombreCaissesParPalette);

        return $this;
    }

    public function getNombreTotalPalettes(): int
    {
        return $this->nombreTotalPalettes;
    }

    public function setNombreTotalPalettes(int|string|null $nombreTotalPalettes): static
    {
        $this->nombreTotalPalettes = max(0, (int) $nombreTotalPalettes);

        return $this;
    }

    public function getQuantiteTotalePreparee(): string
    {
        return $this->quantiteTotalePreparee;
    }

    public function setQuantiteTotalePreparee(int|float|string|null $quantiteTotalePreparee): static
    {
        $this->quantiteTotalePreparee = $this->decimal($quantiteTotalePreparee, 3);

        return $this;
    }

    public function getTunnel(): ?string
    {
        return $this->tunnel;
    }

    public function setTunnel(?string $tunnel): static
    {
        $this->tunnel = $this->nullableString($tunnel);

        return $this;
    }

    public function getDateEntreeTunnel(): ?\DateTimeImmutable
    {
        return $this->dateEntreeTunnel;
    }

    public function setDateEntreeTunnel(?\DateTimeImmutable $dateEntreeTunnel): static
    {
        $this->dateEntreeTunnel = $dateEntreeTunnel;

        return $this;
    }

    public function getHeureEntreeTunnel(): ?\DateTimeImmutable
    {
        return $this->heureEntreeTunnel;
    }

    public function setHeureEntreeTunnel(?\DateTimeImmutable $heureEntreeTunnel): static
    {
        $this->heureEntreeTunnel = $heureEntreeTunnel;

        return $this;
    }

    public function getHeureSortieTunnel(): ?\DateTimeImmutable
    {
        return $this->heureSortieTunnel;
    }

    public function setHeureSortieTunnel(?\DateTimeImmutable $heureSortieTunnel): static
    {
        $this->heureSortieTunnel = $heureSortieTunnel;

        return $this;
    }

    public function getTemperatureTunnel(): ?string
    {
        return $this->temperatureTunnel;
    }

    public function setTemperatureTunnel(int|float|string|null $temperatureTunnel): static
    {
        $this->temperatureTunnel = $this->nullableDecimal($temperatureTunnel);

        return $this;
    }

    public function getDateSortieTunnel(): ?\DateTimeImmutable
    {
        return $this->dateSortieTunnel;
    }

    public function setDateSortieTunnel(?\DateTimeImmutable $dateSortieTunnel): static
    {
        $this->dateSortieTunnel = $dateSortieTunnel;

        return $this;
    }

    public function getTemperatureCoeurProduit(): ?string
    {
        return $this->temperatureCoeurProduit;
    }

    public function setTemperatureCoeurProduit(int|float|string|null $temperatureCoeurProduit): static
    {
        $this->temperatureCoeurProduit = $this->nullableDecimal($temperatureCoeurProduit);

        return $this;
    }

    public function getQuantiteCongelee(): string
    {
        return $this->quantiteCongelee;
    }

    public function setQuantiteCongelee(int|float|string|null $quantiteCongelee): static
    {
        $this->quantiteCongelee = $this->decimal($quantiteCongelee, 3);

        return $this;
    }

    public function getChambreFroide(): ?string
    {
        return $this->chambreFroide;
    }

    public function setChambreFroide(?string $chambreFroide): static
    {
        $this->chambreFroide = $this->nullableString($chambreFroide);

        return $this;
    }

    public function getTemperatureChambre(): ?string
    {
        return $this->temperatureChambre;
    }

    public function setTemperatureChambre(int|float|string|null $temperatureChambre): static
    {
        $this->temperatureChambre = $this->nullableDecimal($temperatureChambre);

        return $this;
    }

    public function getDateEntreeStockage(): ?\DateTimeImmutable
    {
        return $this->dateEntreeStockage;
    }

    public function setDateEntreeStockage(?\DateTimeImmutable $dateEntreeStockage): static
    {
        $this->dateEntreeStockage = $dateEntreeStockage;

        return $this;
    }

    public function getHeureEntreeStockage(): ?\DateTimeImmutable
    {
        return $this->heureEntreeStockage;
    }

    public function setHeureEntreeStockage(?\DateTimeImmutable $heureEntreeStockage): static
    {
        $this->heureEntreeStockage = $heureEntreeStockage;

        return $this;
    }

    public function getQuantiteStockee(): string
    {
        return $this->quantiteStockee;
    }

    public function setQuantiteStockee(int|float|string|null $quantiteStockee): static
    {
        $this->quantiteStockee = $this->decimal($quantiteStockee, 3);

        return $this;
    }

    public function getDateConditionnement(): ?\DateTimeImmutable
    {
        return $this->dateConditionnement;
    }

    public function setDateConditionnement(?\DateTimeImmutable $dateConditionnement): static
    {
        $this->dateConditionnement = $dateConditionnement;

        return $this;
    }

    public function getHeureDebutConditionnement(): ?\DateTimeImmutable
    {
        return $this->heureDebutConditionnement;
    }

    public function setHeureDebutConditionnement(?\DateTimeImmutable $heureDebutConditionnement): static
    {
        $this->heureDebutConditionnement = $heureDebutConditionnement;

        return $this;
    }

    public function getHeureFinConditionnement(): ?\DateTimeImmutable
    {
        return $this->heureFinConditionnement;
    }

    public function setHeureFinConditionnement(?\DateTimeImmutable $heureFinConditionnement): static
    {
        $this->heureFinConditionnement = $heureFinConditionnement;

        return $this;
    }

    public function getProduitConditionne(): ?string
    {
        return $this->produitConditionne;
    }

    public function setProduitConditionne(?string $produitConditionne): static
    {
        $this->produitConditionne = $this->nullableString($produitConditionne);

        return $this;
    }

    public function getQuantiteConditionnee(): string
    {
        return $this->quantiteConditionnee;
    }

    public function setQuantiteConditionnee(int|float|string|null $quantiteConditionnee): static
    {
        $this->quantiteConditionnee = $this->decimal($quantiteConditionnee, 3);

        return $this;
    }

    public function getPoidsNet(): string
    {
        return $this->poidsNet;
    }

    public function setPoidsNet(int|float|string|null $poidsNet): static
    {
        $this->poidsNet = $this->decimal($poidsNet, 3);

        return $this;
    }

    public function getPoidsDechetsEmballage(): string
    {
        return $this->poidsDechetsEmballage;
    }

    public function setPoidsDechetsEmballage(int|float|string|null $poidsDechetsEmballage): static
    {
        $this->poidsDechetsEmballage = $this->decimal($poidsDechetsEmballage, 3);

        return $this;
    }

    public function getPoidsPertesEmballage(): string
    {
        return $this->poidsPertesEmballage;
    }

    public function setPoidsPertesEmballage(int|float|string|null $poidsPertesEmballage): static
    {
        $this->poidsPertesEmballage = $this->decimal($poidsPertesEmballage, 3);

        return $this;
    }

    public function getCoutHoraireEmballage(): string
    {
        return $this->coutHoraireEmballage;
    }

    public function setCoutHoraireEmballage(int|float|string|null $coutHoraireEmballage): static
    {
        $this->coutHoraireEmballage = $this->decimal($coutHoraireEmballage);

        return $this;
    }

    public function getCoutEmballage(): string
    {
        return $this->coutEmballage;
    }

    public function setCoutEmballage(int|float|string|null $coutEmballage): static
    {
        $this->coutEmballage = $this->decimal($coutEmballage);

        return $this;
    }

    public function refreshCoutEmballage(): static
    {
        $this->setCoutEmballage($this->getDureeConditionnementHeuresValue() * $this->getCoutHoraireEmballageValue());

        return $this;
    }

    public function getTemperatureStockage(): ?string
    {
        return $this->temperatureStockage;
    }

    public function setTemperatureStockage(int|float|string|null $temperatureStockage): static
    {
        $this->temperatureStockage = $this->nullableDecimal($temperatureStockage);

        return $this;
    }

    public function getChambreRemiseEnChambre(): ?string
    {
        return $this->chambreRemiseEnChambre;
    }

    public function setChambreRemiseEnChambre(?string $chambreRemiseEnChambre): static
    {
        $this->chambreRemiseEnChambre = $this->nullableString($chambreRemiseEnChambre);

        return $this;
    }

    public function getDateRemiseEnChambre(): ?\DateTimeImmutable
    {
        return $this->dateRemiseEnChambre;
    }

    public function setDateRemiseEnChambre(?\DateTimeImmutable $dateRemiseEnChambre): static
    {
        $this->dateRemiseEnChambre = $dateRemiseEnChambre;

        return $this;
    }

    public function getHeureRemiseEnChambre(): ?\DateTimeImmutable
    {
        return $this->heureRemiseEnChambre;
    }

    public function setHeureRemiseEnChambre(?\DateTimeImmutable $heureRemiseEnChambre): static
    {
        $this->heureRemiseEnChambre = $heureRemiseEnChambre;

        return $this;
    }

    public function getTemperatureChambreRemise(): ?string
    {
        return $this->temperatureChambreRemise;
    }

    public function setTemperatureChambreRemise(int|float|string|null $temperatureChambreRemise): static
    {
        $this->temperatureChambreRemise = $this->nullableDecimal($temperatureChambreRemise);

        return $this;
    }

    public function getTemperatureProduitRemise(): ?string
    {
        return $this->temperatureProduitRemise;
    }

    public function setTemperatureProduitRemise(int|float|string|null $temperatureProduitRemise): static
    {
        $this->temperatureProduitRemise = $this->nullableDecimal($temperatureProduitRemise);

        return $this;
    }

    public function getQuantiteRemiseEnChambre(): string
    {
        return $this->quantiteRemiseEnChambre;
    }

    public function setQuantiteRemiseEnChambre(int|float|string|null $quantiteRemiseEnChambre): static
    {
        $this->quantiteRemiseEnChambre = $this->decimal($quantiteRemiseEnChambre, 3);

        return $this;
    }

    public function getQuantiteTotaleExpediee(): string
    {
        return $this->quantiteTotaleExpediee;
    }

    public function setQuantiteTotaleExpediee(int|float|string|null $quantiteTotaleExpediee): static
    {
        $this->quantiteTotaleExpediee = $this->decimal($quantiteTotaleExpediee, 3);

        return $this;
    }

    public function getDestinationFinaleClient(): ?string
    {
        return $this->destinationFinaleClient;
    }

    public function setDestinationFinaleClient(?string $destinationFinaleClient): static
    {
        $this->destinationFinaleClient = $this->nullableString($destinationFinaleClient);

        return $this;
    }

    public function getExpeditionDateDepart(): ?\DateTimeImmutable
    {
        return $this->expeditionDateDepart;
    }

    public function setExpeditionDateDepart(?\DateTimeImmutable $expeditionDateDepart): static
    {
        $this->expeditionDateDepart = $expeditionDateDepart;

        return $this;
    }

    public function getExpeditionHeureDepart(): ?\DateTimeImmutable
    {
        return $this->expeditionHeureDepart;
    }

    public function setExpeditionHeureDepart(?\DateTimeImmutable $expeditionHeureDepart): static
    {
        $this->expeditionHeureDepart = $expeditionHeureDepart;

        return $this;
    }

    public function getExpeditionMatriculeVehicule(): ?string
    {
        return $this->expeditionMatriculeVehicule;
    }

    public function setExpeditionMatriculeVehicule(?string $expeditionMatriculeVehicule): static
    {
        $this->expeditionMatriculeVehicule = $this->nullableString($expeditionMatriculeVehicule);

        return $this;
    }

    public function getExpeditionChauffeur(): ?string
    {
        return $this->expeditionChauffeur;
    }

    public function setExpeditionChauffeur(?string $expeditionChauffeur): static
    {
        $this->expeditionChauffeur = $this->nullableString($expeditionChauffeur);

        return $this;
    }

    public function getExpeditionResponsableChargement(): ?string
    {
        return $this->expeditionResponsableChargement;
    }

    public function setExpeditionResponsableChargement(?string $expeditionResponsableChargement): static
    {
        $this->expeditionResponsableChargement = $this->nullableString($expeditionResponsableChargement);

        return $this;
    }

    public function getExpeditionTemperatureProduit(): ?string
    {
        return $this->expeditionTemperatureProduit;
    }

    public function setExpeditionTemperatureProduit(int|float|string|null $expeditionTemperatureProduit): static
    {
        $this->expeditionTemperatureProduit = $this->nullableDecimal($expeditionTemperatureProduit);

        return $this;
    }

    public function getExpeditionNumeroPlomb(): ?string
    {
        return $this->expeditionNumeroPlomb;
    }

    public function setExpeditionNumeroPlomb(?string $expeditionNumeroPlomb): static
    {
        $this->expeditionNumeroPlomb = $this->nullableString($expeditionNumeroPlomb);

        return $this;
    }

    public function getExpeditionObservations(): ?string
    {
        return $this->expeditionObservations;
    }

    public function setExpeditionObservations(?string $expeditionObservations): static
    {
        $this->expeditionObservations = $this->nullableString($expeditionObservations);

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $this->nullableString($observations);

        return $this;
    }

    public function getResponsableProduction(): ?string
    {
        return $this->responsableProduction;
    }

    public function setResponsableProduction(?string $responsableProduction): static
    {
        $this->responsableProduction = $this->nullableString($responsableProduction);

        return $this;
    }

    public function getSignatureResponsable(): ?string
    {
        return $this->signatureResponsable;
    }

    public function setSignatureResponsable(?string $signatureResponsable): static
    {
        $this->signatureResponsable = $this->nullableString($signatureResponsable);

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!isset(self::STATUS_LABELS[$statut])) {
            throw new \InvalidArgumentException('Statut de reception invalide.');
        }

        $this->statut = $statut;

        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUS_LABELS[$this->statut] ?? $this->statut;
    }

    public function getStatutBadgeClass(): string
    {
        return self::STATUS_BADGES[$this->statut] ?? 'text-bg-light';
    }

    public function getQuantiteUtiliseeProduction(): string
    {
        return $this->quantiteUtiliseeProduction;
    }

    public function setQuantiteUtiliseeProduction(int|float|string|null $quantiteUtiliseeProduction): static
    {
        $this->quantiteUtiliseeProduction = $this->decimal($quantiteUtiliseeProduction, 3);

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getTreatmentStartedAt(): ?\DateTimeImmutable
    {
        return $this->treatmentStartedAt;
    }

    public function setTreatmentStartedAt(?\DateTimeImmutable $treatmentStartedAt): static
    {
        $this->treatmentStartedAt = $treatmentStartedAt;

        return $this;
    }

    public function getStoredAt(): ?\DateTimeImmutable
    {
        return $this->storedAt;
    }

    public function setStoredAt(?\DateTimeImmutable $storedAt): static
    {
        $this->storedAt = $storedAt;

        return $this;
    }

    public function getRemiseEnChambreAt(): ?\DateTimeImmutable
    {
        return $this->remiseEnChambreAt;
    }

    public function setRemiseEnChambreAt(?\DateTimeImmutable $remiseEnChambreAt): static
    {
        $this->remiseEnChambreAt = $remiseEnChambreAt;

        return $this;
    }

    public function getExpeditedAt(): ?\DateTimeImmutable
    {
        return $this->expeditedAt;
    }

    public function setExpeditedAt(?\DateTimeImmutable $expeditedAt): static
    {
        $this->expeditedAt = $expeditedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getBlockedAt(): ?\DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function setBlockedAt(?\DateTimeImmutable $blockedAt): static
    {
        $this->blockedAt = $blockedAt;

        return $this;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): static
    {
        $this->blockReason = $this->nullableString($blockReason);

        return $this;
    }

    public function getReceivedBy(): ?User
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?User $receivedBy): static
    {
        $this->receivedBy = $receivedBy;

        return $this;
    }

    public function getTreatmentStartedBy(): ?User
    {
        return $this->treatmentStartedBy;
    }

    public function setTreatmentStartedBy(?User $treatmentStartedBy): static
    {
        $this->treatmentStartedBy = $treatmentStartedBy;

        return $this;
    }

    public function getStoredBy(): ?User
    {
        return $this->storedBy;
    }

    public function setStoredBy(?User $storedBy): static
    {
        $this->storedBy = $storedBy;

        return $this;
    }

    public function getRemiseEnChambreBy(): ?User
    {
        return $this->remiseEnChambreBy;
    }

    public function setRemiseEnChambreBy(?User $remiseEnChambreBy): static
    {
        $this->remiseEnChambreBy = $remiseEnChambreBy;

        return $this;
    }

    public function getExpeditedBy(): ?User
    {
        return $this->expeditedBy;
    }

    public function setExpeditedBy(?User $expeditedBy): static
    {
        $this->expeditedBy = $expeditedBy;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getBlockedBy(): ?User
    {
        return $this->blockedBy;
    }

    public function setBlockedBy(?User $blockedBy): static
    {
        $this->blockedBy = $blockedBy;

        return $this;
    }

    /** @return Collection<int, FishReceptionStorageMovement> */
    public function getStorageMovements(): Collection
    {
        return $this->storageMovements;
    }

    public function addStorageMovement(FishReceptionStorageMovement $storageMovement): static
    {
        if (!$this->storageMovements->contains($storageMovement)) {
            $this->storageMovements->add($storageMovement);
            $storageMovement->setReception($this);
        }

        return $this;
    }

    public function removeStorageMovement(FishReceptionStorageMovement $storageMovement): static
    {
        if ($this->storageMovements->removeElement($storageMovement) && $storageMovement->getReception() === $this) {
            $storageMovement->setReception(null);
        }

        return $this;
    }

    /** @return Collection<int, CoutRevient> */
    public function getCoutRevients(): Collection
    {
        return $this->coutRevients;
    }

    public function getQuantiteReceptionneeValue(): float
    {
        return (float) $this->quantiteReceptionnee;
    }

    public function getQuantiteStockInitialEntreeValue(): float
    {
        return $this->sumStorageMovements([
            FishReceptionStorageMovement::TYPE_INITIAL_ENTRY,
        ]);
    }

    public function getQuantiteStockInitialRetourValue(): float
    {
        return $this->sumStorageMovements([
            FishReceptionStorageMovement::TYPE_INITIAL_RETURN,
        ]);
    }

    public function getQuantiteStockInitialSortieValue(): float
    {
        return abs($this->sumStorageMovements([
            FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
        ]));
    }

    public function getQuantiteDisponibleStockageInitialValue(): float
    {
        return max(0.0, $this->getQuantiteReceptionneeValue() - $this->getQuantiteStockInitialEntreeValue());
    }

    public function getQuantiteDisponibleStockInitialValue(): float
    {
        return max(0.0, $this->getQuantiteStockInitialEntreeValue() + $this->getQuantiteStockInitialRetourValue() - $this->getQuantiteStockInitialSortieValue());
    }

    /** @return array<string, float> */
    public function getStockInitialDisponibleParEmplacement(): array
    {
        $stocks = [];
        foreach ($this->storageMovements as $movement) {
            if ($movement->getStorageStage() !== FishReceptionStorageMovement::STAGE_INITIAL) {
                continue;
            }

            $location = trim($movement->getLocation());
            if ($location === '') {
                continue;
            }

            $stocks[$location] = ($stocks[$location] ?? 0.0) + $movement->getQuantityKgValue();
        }

        return array_filter($stocks, static fn (float $quantity): bool => $quantity > 0.001);
    }

    /** @return list<string> */
    public function getStockInitialEmplacementsDisponibles(): array
    {
        return array_keys($this->getStockInitialDisponibleParEmplacement());
    }

    public function getQuantiteUtiliseeProductionValue(): float
    {
        return (float) $this->quantiteUtiliseeProduction;
    }

    public function getQuantiteTotalePrepareeValue(): float
    {
        return (float) $this->quantiteTotalePreparee;
    }

    public function getQuantiteEnTraitementValue(): float
    {
        $sortieTraitement = max(0.0, $this->getQuantiteStockInitialSortieValue() - $this->getQuantiteStockInitialRetourValue());

        return max(0.0, min($this->getQuantiteTotalePrepareeValue(), $sortieTraitement));
    }

    public function getQuantiteCongeleeValue(): float
    {
        return (float) $this->quantiteCongelee;
    }

    public function getDureeTunnelHeuresValue(): float
    {
        $start = $this->combineDateAndTime($this->dateEntreeTunnel ?? $this->dateSortieTunnel, $this->heureEntreeTunnel);
        $end = $this->combineDateAndTime($this->dateSortieTunnel ?? $this->dateEntreeTunnel, $this->heureSortieTunnel);

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return 0.0;
        }

        if ($end < $start) {
            $end = $end->modify('+1 day');
        }

        return max(0, $end->getTimestamp() - $start->getTimestamp()) / 3600;
    }

    public function getQuantiteStockeeValue(): float
    {
        return (float) $this->quantiteStockee;
    }

    public function getQuantiteConditionneeValue(): float
    {
        return (float) $this->quantiteConditionnee;
    }

    public function getQuantiteRemiseEnChambreValue(): float
    {
        return (float) $this->quantiteRemiseEnChambre;
    }

    public function getPoidsNetValue(): float
    {
        return (float) $this->poidsNet;
    }

    public function getPoidsDechetsEmballageValue(): float
    {
        return (float) $this->poidsDechetsEmballage;
    }

    public function getPoidsPertesEmballageValue(): float
    {
        return (float) $this->poidsPertesEmballage;
    }

    public function getCoutHoraireEmballageValue(): float
    {
        return (float) $this->coutHoraireEmballage;
    }

    public function getCoutEmballageValue(): float
    {
        return (float) $this->coutEmballage;
    }

    public function getTotalSortieEmballageValue(): float
    {
        return $this->getPoidsNetValue() + $this->getPoidsDechetsEmballageValue() + $this->getPoidsPertesEmballageValue();
    }

    public function getEcartEmballageValue(): float
    {
        return $this->getQuantiteConditionneeValue() - $this->getTotalSortieEmballageValue();
    }

    public function getDureeConditionnementHeuresValue(): float
    {
        $start = $this->combineDateAndTime($this->dateConditionnement, $this->heureDebutConditionnement);
        $end = $this->combineDateAndTime($this->dateConditionnement, $this->heureFinConditionnement);

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return 0.0;
        }

        if ($end < $start) {
            $end = $end->modify('+1 day');
        }

        return max(0, $end->getTimestamp() - $start->getTimestamp()) / 3600;
    }

    public function getDureeCristallisationHeuresValue(): float
    {
        return $this->hoursBetween(
            $this->combineDateAndTime($this->dateEntreeStockage, $this->heureEntreeStockage),
            $this->combineDateAndTime($this->dateConditionnement, $this->heureDebutConditionnement),
        );
    }

    public function getDureeRemiseChambreAvantExpeditionHeuresValue(): float
    {
        return $this->hoursBetween(
            $this->combineDateAndTime($this->dateRemiseEnChambre, $this->heureRemiseEnChambre),
            $this->combineDateAndTime($this->expeditionDateDepart, $this->expeditionHeureDepart),
        );
    }

    public function getCoutKgEmballageValue(): float
    {
        return $this->getPoidsNetValue() > 0 ? $this->getCoutEmballageValue() / $this->getPoidsNetValue() : 0.0;
    }

    public function getQuantiteTotaleExpedieeValue(): float
    {
        return (float) $this->quantiteTotaleExpediee;
    }

    public function getQuantiteDisponibleValue(): float
    {
        return $this->getQuantiteDisponibleReceptionValue();
    }

    public function getQuantiteDisponibleReceptionValue(): float
    {
        return $this->getQuantiteDisponibleStockageInitialValue();
    }

    public function getQuantiteDisponibleTraitementSourceValue(): float
    {
        return $this->getQuantiteDisponibleStockInitialValue();
    }

    public function getQuantiteDisponibleTraitementValue(): float
    {
        return max(0.0, $this->getQuantiteEnTraitementValue() - $this->getQuantiteCongeleeValue());
    }

    public function getQuantiteDisponibleCristallisationValue(): float
    {
        return max(0.0, $this->getQuantiteStockeeValue() - $this->getQuantiteConditionneeValue());
    }

    public function getQuantiteDisponibleEmballageValue(): float
    {
        return max(0.0, $this->getQuantiteConditionneeValue() - $this->getQuantiteRemiseEnChambreValue());
    }

    public function getQuantiteDisponibleCongelationValue(): float
    {
        return max(0.0, $this->getQuantiteCongeleeValue() - $this->getQuantiteStockeeValue());
    }

    public function getQuantiteDisponibleStockageValue(): float
    {
        return max(0.0, $this->getQuantiteRemiseEnChambreValue() - $this->getQuantiteTotaleExpedieeValue());
    }

    public function getQuantiteDisponibleProductionValue(): float
    {
        return max(0.0, $this->getQuantiteReceptionneeValue() - $this->getQuantiteUtiliseeProductionValue());
    }

    public function getWorkflowAvailableForStage(string $stage): float
    {
        return match ($stage) {
            'traitement' => $this->getQuantiteDisponibleTraitementSourceValue(),
            'congelation' => $this->getQuantiteDisponibleTraitementValue(),
            'stockage' => $this->getQuantiteDisponibleCongelationValue(),
            'emballage' => $this->getQuantiteDisponibleCristallisationValue(),
            'expedition' => $this->getQuantiteDisponibleStockageValue(),
            default => $this->getQuantiteDisponibleReceptionValue(),
        };
    }

    public function getWorkflowMovedForStage(string $stage): float
    {
        return match ($stage) {
            'reception' => $this->getQuantiteStockInitialEntreeValue(),
            'congelation' => $this->getQuantiteCongeleeValue(),
            'stockage' => $this->getQuantiteStockeeValue(),
            'emballage' => $this->getQuantiteConditionneeValue(),
            'expedition' => $this->getQuantiteTotaleExpedieeValue(),
            default => $this->getQuantiteTotalePrepareeValue(),
        };
    }

    public function getEcartBlReceptionValue(): float
    {
        return $this->getQuantiteReceptionneeValue() - (float) $this->quantiteIndiqueeBl;
    }

    public function getTauxUtilisation(): float
    {
        $received = $this->getQuantiteReceptionneeValue();

        return $received > 0 ? ($this->getQuantiteTotalePrepareeValue() / $received) * 100 : 0.0;
    }

    public function getUsageLabel(): string
    {
        if ($this->getQuantiteTotalePrepareeValue() <= 0) {
            return 'Non traite';
        }

        if ($this->getQuantiteDisponibleValue() <= 0.001) {
            return 'Tout envoye';
        }

        return 'Partielle';
    }

    public function getUsageBadgeClass(): string
    {
        if ($this->getQuantiteTotalePrepareeValue() <= 0) {
            return 'text-bg-secondary';
        }

        if ($this->getQuantiteDisponibleValue() <= 0.001) {
            return 'text-bg-dark';
        }

        return 'text-bg-warning';
    }

    public function isLocked(): bool
    {
        return in_array($this->statut, [self::STATUS_CLOSED, self::STATUS_BLOCKED], true);
    }

    public function canBeUsedInProduction(): bool
    {
        return !$this->isDeleted()
            && !$this->isLocked()
            && $this->statut !== self::STATUS_DRAFT
            && $this->getQuantiteDisponibleProductionValue() > 0.001;
    }

    public function getDisplayName(): string
    {
        return trim(sprintf(
            '%s - %s - %s kg dispo',
            (string) $this->numeroReception,
            (string) $this->especePoisson,
            number_format($this->getQuantiteDisponibleProductionValue(), 3, ',', ' '),
        ));
    }

    public function floatValue(string $property): float
    {
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException('Champ numerique inconnu.');
        }

        return (float) $this->{$property};
    }

    /** @param list<string> $types */
    private function sumStorageMovements(array $types): float
    {
        $total = 0.0;
        foreach ($this->storageMovements as $movement) {
            if (in_array($movement->getMovementType(), $types, true)) {
                $total += $movement->getQuantityKgValue();
            }
        }

        return $total;
    }

    private function combineDateAndTime(?\DateTimeImmutable $date, ?\DateTimeImmutable $time): ?\DateTimeImmutable
    {
        if (!$date instanceof \DateTimeImmutable || !$time instanceof \DateTimeImmutable) {
            return null;
        }

        return $date->setTime((int) $time->format('H'), (int) $time->format('i'));
    }

    private function hoursBetween(?\DateTimeImmutable $start, ?\DateTimeImmutable $end): float
    {
        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable || $end < $start) {
            return 0.0;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) / 3600;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableDecimal(int|float|string|null $value, int $scale = 2): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format((float) $normalized, $scale, '.', '');
    }

    private function decimal(int|float|string|null $value, int $scale = 2): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format(max(0.0, (float) $normalized), $scale, '.', '');
    }
}
