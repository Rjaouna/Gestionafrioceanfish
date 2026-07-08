<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\FishYieldStudyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FishYieldStudyRepository::class)]
#[ORM\Table(name: 'fish_yield_study')]
#[ORM\UniqueConstraint(name: 'uniq_fish_yield_study_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_fish_yield_study_date', columns: ['study_date'])]
#[ORM\Index(name: 'idx_fish_yield_study_client', columns: ['client_name'])]
#[ORM\Index(name: 'idx_fish_yield_study_species', columns: ['species_name'])]
#[ORM\Index(name: 'idx_fish_yield_study_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_fish_yield_study_updated_by', columns: ['updated_by_id'])]
#[ORM\Index(name: 'idx_fish_yield_study_deleted_by', columns: ['deleted_by_id'])]
#[UniqueEntity(fields: ['reference'], message: 'Cette reference existe deja.')]
class FishYieldStudy
{
    use SoftDeleteTrait;
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\Length(max: 80)]
    private ?string $reference = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $studyDate = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $clientName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $speciesName = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasMixedFish = false;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $mixedFishName = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $rawBoxWeight = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $thawedBoxWeight = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $piecesPerKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $finishedProductWeight = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $wasteWeight = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $lossWeight = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $containerWeight = '0.000';

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $operatorName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1800)]
    private ?string $observations = null;

    public function __construct()
    {
        $this->studyDate = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $reference = strtoupper(trim((string) $reference));
        $this->reference = $reference !== '' ? $reference : null;

        return $this;
    }

    public function getStudyDate(): ?\DateTimeImmutable
    {
        return $this->studyDate;
    }

    public function setStudyDate(?\DateTimeImmutable $studyDate): static
    {
        $this->studyDate = $studyDate;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $clientName = trim((string) $clientName);
        $this->clientName = $clientName !== '' ? $clientName : null;

        return $this;
    }

    public function getSpeciesName(): ?string
    {
        return $this->speciesName;
    }

    public function setSpeciesName(?string $speciesName): static
    {
        $this->speciesName = trim((string) $speciesName);

        return $this;
    }

    public function hasMixedFish(): bool
    {
        return $this->hasMixedFish;
    }

    public function setHasMixedFish(bool $hasMixedFish): static
    {
        $this->hasMixedFish = $hasMixedFish;
        if (!$hasMixedFish) {
            $this->mixedFishName = null;
        }

        return $this;
    }

    public function getMixedFishName(): ?string
    {
        return $this->mixedFishName;
    }

    public function setMixedFishName(?string $mixedFishName): static
    {
        $mixedFishName = trim((string) $mixedFishName);
        $this->mixedFishName = $mixedFishName !== '' ? $mixedFishName : null;

        return $this;
    }

    public function getRawBoxWeight(): string
    {
        return $this->rawBoxWeight;
    }

    public function setRawBoxWeight(int|float|string|null $rawBoxWeight): static
    {
        $this->rawBoxWeight = $this->decimal($rawBoxWeight, 3);

        return $this;
    }

    public function getThawedBoxWeight(): string
    {
        return $this->thawedBoxWeight;
    }

    public function setThawedBoxWeight(int|float|string|null $thawedBoxWeight): static
    {
        $this->thawedBoxWeight = $this->decimal($thawedBoxWeight, 3);

        return $this;
    }

    public function getPiecesPerKg(): string
    {
        return $this->piecesPerKg;
    }

    public function setPiecesPerKg(int|float|string|null $piecesPerKg): static
    {
        $this->piecesPerKg = $this->decimal($piecesPerKg, 2);

        return $this;
    }

    public function getFinishedProductWeight(): string
    {
        return $this->finishedProductWeight;
    }

    public function setFinishedProductWeight(int|float|string|null $finishedProductWeight): static
    {
        $this->finishedProductWeight = $this->decimal($finishedProductWeight, 3);

        return $this;
    }

    public function getWasteWeight(): string
    {
        return $this->wasteWeight;
    }

    public function setWasteWeight(int|float|string|null $wasteWeight): static
    {
        $this->wasteWeight = $this->decimal($wasteWeight, 3);

        return $this;
    }

    public function getLossWeight(): string
    {
        return $this->lossWeight;
    }

    public function setLossWeight(int|float|string|null $lossWeight): static
    {
        $this->lossWeight = $this->decimal($lossWeight, 3);

        return $this;
    }

    public function getContainerWeight(): string
    {
        return $this->containerWeight;
    }

    public function setContainerWeight(int|float|string|null $containerWeight): static
    {
        $this->containerWeight = $this->decimal($containerWeight, 3);

        return $this;
    }

    public function getOperatorName(): ?string
    {
        return $this->operatorName;
    }

    public function setOperatorName(?string $operatorName): static
    {
        $operatorName = trim((string) $operatorName);
        $this->operatorName = $operatorName !== '' ? $operatorName : null;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $observations = trim((string) $observations);
        $this->observations = $observations !== '' ? $observations : null;

        return $this;
    }

    public function rawBoxWeightValue(): float
    {
        return (float) $this->rawBoxWeight;
    }

    public function thawedBoxWeightValue(): float
    {
        return (float) $this->thawedBoxWeight;
    }

    public function piecesPerKgValue(): float
    {
        return (float) $this->piecesPerKg;
    }

    public function finishedProductWeightValue(): float
    {
        return (float) $this->finishedProductWeight;
    }

    public function wasteWeightValue(): float
    {
        return (float) $this->wasteWeight;
    }

    public function lossWeightValue(): float
    {
        return (float) $this->lossWeight;
    }

    public function containerWeightValue(): float
    {
        return (float) $this->containerWeight;
    }

    public function waterWeightValue(): float
    {
        return max(0.0, $this->rawBoxWeightValue() - $this->thawedBoxWeightValue());
    }

    public function waterRate(): float
    {
        return $this->rate($this->waterWeightValue(), $this->rawBoxWeightValue());
    }

    public function totalWorkedOutputValue(): float
    {
        return $this->finishedProductWeightValue() + $this->wasteWeightValue() + $this->lossWeightValue();
    }

    public function processGapValue(): float
    {
        return $this->thawedBoxWeightValue() - $this->totalWorkedOutputValue();
    }

    public function yieldRate(): float
    {
        return $this->rate($this->finishedProductWeightValue(), $this->thawedBoxWeightValue());
    }

    public function wasteRate(): float
    {
        return $this->rate($this->wasteWeightValue(), $this->thawedBoxWeightValue());
    }

    public function lossRate(): float
    {
        return $this->rate($this->lossWeightValue(), $this->thawedBoxWeightValue());
    }

    public function containerWaterEstimateValue(): float
    {
        return $this->containerRatioEstimate($this->waterWeightValue());
    }

    public function containerThawedEstimateValue(): float
    {
        return $this->containerRatioEstimate($this->thawedBoxWeightValue());
    }

    public function containerFinishedEstimateValue(): float
    {
        return $this->containerRatioEstimate($this->finishedProductWeightValue());
    }

    public function containerWasteEstimateValue(): float
    {
        return $this->containerRatioEstimate($this->wasteWeightValue());
    }

    public function containerLossEstimateValue(): float
    {
        return $this->containerRatioEstimate($this->lossWeightValue());
    }

    public function diagnosticLabel(): string
    {
        if ($this->rawBoxWeightValue() <= 0 || $this->thawedBoxWeightValue() <= 0) {
            return 'Etude incomplete';
        }

        if ($this->waterRate() > 20 || abs($this->processGapValue()) > 0.1) {
            return 'A verifier';
        }

        if ($this->yieldRate() >= 45 && $this->yieldRate() <= 60 && $this->lossRate() <= 10) {
            return 'Rendement correct';
        }

        return 'A surveiller';
    }

    public function diagnosticBadgeClass(): string
    {
        return match ($this->diagnosticLabel()) {
            'Rendement correct' => 'text-bg-success',
            'A verifier' => 'text-bg-danger',
            'A surveiller' => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public function floatValue(string $property): float
    {
        $method = $property.'Value';
        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException('Champ numerique inconnu.');
        }

        return (float) $this->{$method}();
    }

    private function containerRatioEstimate(float $sampleValue): float
    {
        $raw = $this->rawBoxWeightValue();
        if ($raw <= 0 || $this->containerWeightValue() <= 0) {
            return 0.0;
        }

        return ($sampleValue / $raw) * $this->containerWeightValue();
    }

    private function rate(float $value, float $total): float
    {
        return $total > 0 ? ($value / $total) * 100 : 0.0;
    }

    private function decimal(int|float|string|null $value, int $scale): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        $normalized = str_replace(',', '.', (string) $value);
        $float = max(0.0, (float) $normalized);

        return number_format($float, $scale, '.', '');
    }
}
