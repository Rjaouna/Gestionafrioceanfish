<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\CoutRevientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoutRevientRepository::class)]
#[ORM\Table(name: 'cout_revient')]
#[ORM\UniqueConstraint(name: 'uniq_cout_revient_numero_lot', fields: ['numeroLot'])]
#[ORM\Index(name: 'idx_cout_revient_date_production', columns: ['date_production'])]
#[ORM\Index(name: 'idx_cout_revient_produit', columns: ['produit'])]
#[ORM\Index(name: 'idx_cout_revient_client', columns: ['client'])]
#[ORM\Index(name: 'idx_cout_revient_statut', columns: ['statut'])]
#[ORM\Index(name: 'idx_cout_revient_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_cout_revient_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_cout_revient_deleted_by', columns: ['deleted_by_id'])]
#[ORM\Index(name: 'idx_cout_revient_validated_by', columns: ['validated_by_id'])]
#[UniqueEntity(fields: ['numeroLot'], message: 'Ce numero de lot existe deja.')]
class CoutRevient
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    public const STATUS_DRAFT = 'brouillon';
    public const STATUS_VALIDATED = 'valide';
    public const STATUS_ARCHIVED = 'archive';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_VALIDATED => 'Valide',
        self::STATUS_ARCHIVED => 'Archive',
    ];

    public const STATUS_BADGES = [
        self::STATUS_DRAFT => 'text-bg-secondary',
        self::STATUS_VALIDATED => 'text-bg-success',
        self::STATUS_ARCHIVED => 'text-bg-dark',
    ];

    public const MODE_HOUR = 'heure';
    public const MODE_KG = 'kg';
    public const MODE_DIRECT = 'montant_direct';

    public const MODE_LABELS = [
        self::MODE_HOUR => 'A l heure',
        self::MODE_KG => 'A la tache / kg',
        self::MODE_DIRECT => 'Montant direct',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $dateProduction = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private ?string $numeroLot = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $produit = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $especePoisson = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $client = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $responsableProduction = null;

    #[ORM\Column(length: 30, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::STATUS_DRAFT, self::STATUS_VALIDATED, self::STATUS_ARCHIVED])]
    private string $statut = self::STATUS_DRAFT;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $observation = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsBrutRecu = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsMisEnProduction = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $prixAchatKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $fraisTransportAchat = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $autresFraisAchat = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutMatierePremiere = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsProduitFini = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsDechets = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $poidsPerte = '0.000';

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, options: ['default' => '0.00'])]
    private string $rendementPourcentage = '0.00';

    #[ORM\Column(length: 30, options: ['default' => self::MODE_DIRECT])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::MODE_HOUR, self::MODE_KG, self::MODE_DIRECT])]
    private string $modeCalculMainOeuvre = self::MODE_DIRECT;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreOperatrices = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $nombreHeures = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutHoraireMoyen = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $prixTacheKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $kgTraitesMainOeuvre = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutMainOeuvreDirect = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutMainOeuvre = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreCartons = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $prixCarton = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $nombreSachets = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $prixSachet = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutEtiquettes = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutFilmPlastique = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $autresCoutEmballage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutEmballageTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutElectricite = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutEau = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutGlace = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutNettoyage = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutMaintenance = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $coutTransportLivraison = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $autresCharges = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutChargesTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutTotalProduction = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $coutRevientKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $prixVenteKg = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $margeKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $margeTotale = '0.00';

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, options: ['default' => '0.00'])]
    private string $tauxMargePourcentage = '0.00';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    /** @var Collection<int, CoutRevientChargeLine> */
    #[ORM\OneToMany(targetEntity: CoutRevientChargeLine::class, mappedBy: 'coutRevient', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $chargeLines;

    public function __construct()
    {
        $this->dateProduction = new \DateTimeImmutable('today');
        $this->chargeLines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateProduction(): ?\DateTimeImmutable
    {
        return $this->dateProduction;
    }

    public function setDateProduction(?\DateTimeImmutable $dateProduction): static
    {
        $this->dateProduction = $dateProduction;

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

    public function getProduit(): ?string
    {
        return $this->produit;
    }

    public function setProduit(string $produit): static
    {
        $this->produit = trim($produit);

        return $this;
    }

    public function getEspecePoisson(): ?string
    {
        return $this->especePoisson;
    }

    public function setEspecePoisson(?string $especePoisson): static
    {
        $especePoisson = trim((string) $especePoisson);
        $this->especePoisson = $especePoisson !== '' ? $especePoisson : null;

        return $this;
    }

    public function getClient(): ?string
    {
        return $this->client;
    }

    public function setClient(?string $client): static
    {
        $client = trim((string) $client);
        $this->client = $client !== '' ? $client : null;

        return $this;
    }

    public function getResponsableProduction(): ?string
    {
        return $this->responsableProduction;
    }

    public function setResponsableProduction(?string $responsableProduction): static
    {
        $responsableProduction = trim((string) $responsableProduction);
        $this->responsableProduction = $responsableProduction !== '' ? $responsableProduction : null;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!isset(self::STATUS_LABELS[$statut])) {
            throw new \InvalidArgumentException('Statut de cout de revient invalide.');
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

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $observation = trim((string) $observation);
        $this->observation = $observation !== '' ? $observation : null;

        return $this;
    }

    public function getPoidsBrutRecu(): string
    {
        return $this->poidsBrutRecu;
    }

    public function setPoidsBrutRecu(int|float|string|null $poidsBrutRecu): static
    {
        $this->poidsBrutRecu = $this->decimal($poidsBrutRecu, 3);

        return $this;
    }

    public function getPoidsMisEnProduction(): string
    {
        return $this->poidsMisEnProduction;
    }

    public function setPoidsMisEnProduction(int|float|string|null $poidsMisEnProduction): static
    {
        $this->poidsMisEnProduction = $this->decimal($poidsMisEnProduction, 3);

        return $this;
    }

    public function getPrixAchatKg(): string
    {
        return $this->prixAchatKg;
    }

    public function setPrixAchatKg(int|float|string|null $prixAchatKg): static
    {
        $this->prixAchatKg = $this->decimal($prixAchatKg);

        return $this;
    }

    public function getFraisTransportAchat(): string
    {
        return $this->fraisTransportAchat;
    }

    public function setFraisTransportAchat(int|float|string|null $fraisTransportAchat): static
    {
        $this->fraisTransportAchat = $this->decimal($fraisTransportAchat);

        return $this;
    }

    public function getAutresFraisAchat(): string
    {
        return $this->autresFraisAchat;
    }

    public function setAutresFraisAchat(int|float|string|null $autresFraisAchat): static
    {
        $this->autresFraisAchat = $this->decimal($autresFraisAchat);

        return $this;
    }

    public function getCoutMatierePremiere(): string
    {
        return $this->coutMatierePremiere;
    }

    public function setCoutMatierePremiere(int|float|string|null $coutMatierePremiere): static
    {
        $this->coutMatierePremiere = $this->decimal($coutMatierePremiere);

        return $this;
    }

    public function getPoidsProduitFini(): string
    {
        return $this->poidsProduitFini;
    }

    public function setPoidsProduitFini(int|float|string|null $poidsProduitFini): static
    {
        $this->poidsProduitFini = $this->decimal($poidsProduitFini, 3);

        return $this;
    }

    public function getPoidsDechets(): string
    {
        return $this->poidsDechets;
    }

    public function setPoidsDechets(int|float|string|null $poidsDechets): static
    {
        $this->poidsDechets = $this->decimal($poidsDechets, 3);

        return $this;
    }

    public function getPoidsPerte(): string
    {
        return $this->poidsPerte;
    }

    public function setPoidsPerte(int|float|string|null $poidsPerte): static
    {
        $this->poidsPerte = $this->decimal($poidsPerte, 3);

        return $this;
    }

    public function getRendementPourcentage(): string
    {
        return $this->rendementPourcentage;
    }

    public function setRendementPourcentage(int|float|string|null $rendementPourcentage): static
    {
        $this->rendementPourcentage = $this->decimal($rendementPourcentage);

        return $this;
    }

    public function getModeCalculMainOeuvre(): string
    {
        return $this->modeCalculMainOeuvre;
    }

    public function setModeCalculMainOeuvre(string $modeCalculMainOeuvre): static
    {
        if (!isset(self::MODE_LABELS[$modeCalculMainOeuvre])) {
            throw new \InvalidArgumentException('Mode de calcul main d oeuvre invalide.');
        }

        $this->modeCalculMainOeuvre = $modeCalculMainOeuvre;

        return $this;
    }

    public function getModeCalculMainOeuvreLabel(): string
    {
        return self::MODE_LABELS[$this->modeCalculMainOeuvre] ?? $this->modeCalculMainOeuvre;
    }

    public function getNombreOperatrices(): int
    {
        return $this->nombreOperatrices;
    }

    public function setNombreOperatrices(int|string|null $nombreOperatrices): static
    {
        $this->nombreOperatrices = max(0, (int) $nombreOperatrices);

        return $this;
    }

    public function getNombreHeures(): string
    {
        return $this->nombreHeures;
    }

    public function setNombreHeures(int|float|string|null $nombreHeures): static
    {
        $this->nombreHeures = $this->decimal($nombreHeures);

        return $this;
    }

    public function getCoutHoraireMoyen(): string
    {
        return $this->coutHoraireMoyen;
    }

    public function setCoutHoraireMoyen(int|float|string|null $coutHoraireMoyen): static
    {
        $this->coutHoraireMoyen = $this->decimal($coutHoraireMoyen);

        return $this;
    }

    public function getPrixTacheKg(): string
    {
        return $this->prixTacheKg;
    }

    public function setPrixTacheKg(int|float|string|null $prixTacheKg): static
    {
        $this->prixTacheKg = $this->decimal($prixTacheKg);

        return $this;
    }

    public function getKgTraitesMainOeuvre(): string
    {
        return $this->kgTraitesMainOeuvre;
    }

    public function setKgTraitesMainOeuvre(int|float|string|null $kgTraitesMainOeuvre): static
    {
        $this->kgTraitesMainOeuvre = $this->decimal($kgTraitesMainOeuvre, 3);

        return $this;
    }

    public function getCoutMainOeuvreDirect(): string
    {
        return $this->coutMainOeuvreDirect;
    }

    public function setCoutMainOeuvreDirect(int|float|string|null $coutMainOeuvreDirect): static
    {
        $this->coutMainOeuvreDirect = $this->decimal($coutMainOeuvreDirect);

        return $this;
    }

    public function getCoutMainOeuvre(): string
    {
        return $this->coutMainOeuvre;
    }

    public function setCoutMainOeuvre(int|float|string|null $coutMainOeuvre): static
    {
        $this->coutMainOeuvre = $this->decimal($coutMainOeuvre);

        return $this;
    }

    public function getNombreCartons(): int
    {
        return $this->nombreCartons;
    }

    public function setNombreCartons(int|string|null $nombreCartons): static
    {
        $this->nombreCartons = max(0, (int) $nombreCartons);

        return $this;
    }

    public function getPrixCarton(): string
    {
        return $this->prixCarton;
    }

    public function setPrixCarton(int|float|string|null $prixCarton): static
    {
        $this->prixCarton = $this->decimal($prixCarton);

        return $this;
    }

    public function getNombreSachets(): int
    {
        return $this->nombreSachets;
    }

    public function setNombreSachets(int|string|null $nombreSachets): static
    {
        $this->nombreSachets = max(0, (int) $nombreSachets);

        return $this;
    }

    public function getPrixSachet(): string
    {
        return $this->prixSachet;
    }

    public function setPrixSachet(int|float|string|null $prixSachet): static
    {
        $this->prixSachet = $this->decimal($prixSachet);

        return $this;
    }

    public function getCoutEtiquettes(): string
    {
        return $this->coutEtiquettes;
    }

    public function setCoutEtiquettes(int|float|string|null $coutEtiquettes): static
    {
        $this->coutEtiquettes = $this->decimal($coutEtiquettes);

        return $this;
    }

    public function getCoutFilmPlastique(): string
    {
        return $this->coutFilmPlastique;
    }

    public function setCoutFilmPlastique(int|float|string|null $coutFilmPlastique): static
    {
        $this->coutFilmPlastique = $this->decimal($coutFilmPlastique);

        return $this;
    }

    public function getAutresCoutEmballage(): string
    {
        return $this->autresCoutEmballage;
    }

    public function setAutresCoutEmballage(int|float|string|null $autresCoutEmballage): static
    {
        $this->autresCoutEmballage = $this->decimal($autresCoutEmballage);

        return $this;
    }

    public function getCoutEmballageTotal(): string
    {
        return $this->coutEmballageTotal;
    }

    public function setCoutEmballageTotal(int|float|string|null $coutEmballageTotal): static
    {
        $this->coutEmballageTotal = $this->decimal($coutEmballageTotal);

        return $this;
    }

    public function getCoutElectricite(): string
    {
        return $this->coutElectricite;
    }

    public function setCoutElectricite(int|float|string|null $coutElectricite): static
    {
        $this->coutElectricite = $this->decimal($coutElectricite);

        return $this;
    }

    public function getCoutEau(): string
    {
        return $this->coutEau;
    }

    public function setCoutEau(int|float|string|null $coutEau): static
    {
        $this->coutEau = $this->decimal($coutEau);

        return $this;
    }

    public function getCoutGlace(): string
    {
        return $this->coutGlace;
    }

    public function setCoutGlace(int|float|string|null $coutGlace): static
    {
        $this->coutGlace = $this->decimal($coutGlace);

        return $this;
    }

    public function getCoutNettoyage(): string
    {
        return $this->coutNettoyage;
    }

    public function setCoutNettoyage(int|float|string|null $coutNettoyage): static
    {
        $this->coutNettoyage = $this->decimal($coutNettoyage);

        return $this;
    }

    public function getCoutMaintenance(): string
    {
        return $this->coutMaintenance;
    }

    public function setCoutMaintenance(int|float|string|null $coutMaintenance): static
    {
        $this->coutMaintenance = $this->decimal($coutMaintenance);

        return $this;
    }

    public function getCoutTransportLivraison(): string
    {
        return $this->coutTransportLivraison;
    }

    public function setCoutTransportLivraison(int|float|string|null $coutTransportLivraison): static
    {
        $this->coutTransportLivraison = $this->decimal($coutTransportLivraison);

        return $this;
    }

    public function getAutresCharges(): string
    {
        return $this->autresCharges;
    }

    public function setAutresCharges(int|float|string|null $autresCharges): static
    {
        $this->autresCharges = $this->decimal($autresCharges);

        return $this;
    }

    public function getCoutChargesTotal(): string
    {
        return $this->coutChargesTotal;
    }

    public function setCoutChargesTotal(int|float|string|null $coutChargesTotal): static
    {
        $this->coutChargesTotal = $this->decimal($coutChargesTotal);

        return $this;
    }

    public function getCoutTotalProduction(): string
    {
        return $this->coutTotalProduction;
    }

    public function setCoutTotalProduction(int|float|string|null $coutTotalProduction): static
    {
        $this->coutTotalProduction = $this->decimal($coutTotalProduction);

        return $this;
    }

    public function getCoutRevientKg(): string
    {
        return $this->coutRevientKg;
    }

    public function setCoutRevientKg(int|float|string|null $coutRevientKg): static
    {
        $this->coutRevientKg = $this->decimal($coutRevientKg);

        return $this;
    }

    public function getPrixVenteKg(): ?string
    {
        return $this->prixVenteKg;
    }

    public function setPrixVenteKg(int|float|string|null $prixVenteKg): static
    {
        $value = trim((string) ($prixVenteKg ?? ''));
        $this->prixVenteKg = $value === '' ? null : $this->decimal($value);

        return $this;
    }

    public function getMargeKg(): string
    {
        return $this->margeKg;
    }

    public function setMargeKg(int|float|string|null $margeKg): static
    {
        $this->margeKg = $this->decimal($margeKg);

        return $this;
    }

    public function getMargeTotale(): string
    {
        return $this->margeTotale;
    }

    public function setMargeTotale(int|float|string|null $margeTotale): static
    {
        $this->margeTotale = $this->decimal($margeTotale);

        return $this;
    }

    public function getTauxMargePourcentage(): string
    {
        return $this->tauxMargePourcentage;
    }

    public function setTauxMargePourcentage(int|float|string|null $tauxMargePourcentage): static
    {
        $this->tauxMargePourcentage = $this->decimal($tauxMargePourcentage);

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;

        return $this;
    }

    /** @return Collection<int, CoutRevientChargeLine> */
    public function getChargeLines(): Collection
    {
        return $this->chargeLines;
    }

    public function addChargeLine(CoutRevientChargeLine $chargeLine): static
    {
        if (!$this->chargeLines->contains($chargeLine)) {
            $this->chargeLines->add($chargeLine);
            $chargeLine->setCoutRevient($this);
        }

        return $this;
    }

    public function removeChargeLine(CoutRevientChargeLine $chargeLine): static
    {
        if ($this->chargeLines->removeElement($chargeLine) && $chargeLine->getCoutRevient() === $this) {
            $chargeLine->setCoutRevient(null);
        }

        return $this;
    }

    public function clearChargeLines(): static
    {
        foreach ($this->chargeLines->toArray() as $line) {
            $this->removeChargeLine($line);
        }

        return $this;
    }

    public function getConfiguredChargesTotal(): float
    {
        return array_sum(array_map(
            static fn (CoutRevientChargeLine $line): float => (float) $line->getTotalAmount(),
            $this->chargeLines->toArray(),
        ));
    }

    public function isValidated(): bool
    {
        return $this->statut === self::STATUS_VALIDATED;
    }

    public function hasPrixVente(): bool
    {
        return $this->prixVenteKg !== null && (float) $this->prixVenteKg > 0;
    }

    public function getRentabiliteLabel(): string
    {
        if (!$this->hasPrixVente()) {
            return 'Sans prix vente';
        }

        $marge = (float) $this->margeKg;
        if ($marge < 0) {
            return 'Non rentable';
        }

        if (abs($marge) < 0.01) {
            return 'Marge nulle';
        }

        return 'Rentable';
    }

    public function getRentabiliteBadgeClass(): string
    {
        if (!$this->hasPrixVente()) {
            return 'text-bg-secondary';
        }

        $marge = (float) $this->margeKg;

        return match (true) {
            $marge < 0 => 'text-bg-danger',
            abs($marge) < 0.01 => 'text-bg-warning',
            default => 'text-bg-success',
        };
    }

    /** @return list<string> */
    public function getAlertMessages(): array
    {
        $alerts = [];
        $misEnProduction = (float) $this->poidsMisEnProduction;
        $totalSortie = (float) $this->poidsProduitFini + (float) $this->poidsDechets + (float) $this->poidsPerte;
        if ($misEnProduction > 0 && abs($totalSortie - $misEnProduction) > 0.001) {
            $alerts[] = 'Attention : le total fini + dechets + pertes ne correspond pas au poids mis en production.';
        }

        if ((float) $this->poidsProduitFini <= 0) {
            $alerts[] = 'Impossible de calculer le cout/kg sans poids fini.';
        }

        $rendement = (float) $this->rendementPourcentage;
        if ($rendement > 100) {
            $alerts[] = 'Rendement impossible.';
        } elseif ($rendement > 0 && $rendement < 40) {
            $alerts[] = 'Rendement faible, verifier pertes et dechets.';
        }

        if ($this->hasPrixVente() && (float) $this->margeKg < 0) {
            $alerts[] = 'Production non rentable.';
        }

        return $alerts;
    }

    public function floatValue(string $property): float
    {
        if (!property_exists($this, $property)) {
            throw new \InvalidArgumentException('Champ numerique inconnu.');
        }

        return (float) $this->{$property};
    }

    private function decimal(int|float|string|null $value, int $scale = 2): string
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($normalized === '' || !is_numeric($normalized)) {
            $normalized = '0';
        }

        return number_format((float) $normalized, $scale, '.', '');
    }
}
