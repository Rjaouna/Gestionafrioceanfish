<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableUserTrait;
use App\Repository\DailyProductionCostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DailyProductionCostRepository::class)]
#[ORM\Table(name: 'daily_production_cost')]
#[ORM\UniqueConstraint(name: 'uniq_daily_production_cost_reference', fields: ['reference'])]
#[ORM\Index(name: 'idx_daily_production_cost_date', columns: ['production_date'])]
#[ORM\Index(name: 'idx_daily_production_cost_created_by', columns: ['created_by_id'])]
#[ORM\Index(name: 'idx_daily_production_cost_updated_by', columns: ['updated_by_id'])]
#[UniqueEntity(fields: ['reference'], message: 'Cette reference existe deja.')]
class DailyProductionCost
{
    use TimestampableUserTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $productionDate = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private ?string $reference = null;

    #[ORM\Column(length: 150, options: ['default' => 'Anchois'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $productName = 'Anchois';

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $responsible = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $rawQuantityKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $finishedProductKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $wasteKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $lossKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    private string $totalOutputKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    private string $weightGapKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, options: ['default' => '0.00'])]
    private string $yieldPercent = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $hourlyWorkers = 0;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $hourlyHours = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $hourlyRate = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $hourlyLaborTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $cleaningKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $cleaningPricePerKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $boxingKg = '0.000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $boxingPricePerKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $otherTaskAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taskLaborTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $fixedSalaryMonthlyTotal = '0.00';

    #[ORM\Column(options: ['default' => 26])]
    #[Assert\Positive]
    private int $fixedSalaryWorkingDays = 26;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $fixedSalaryDailyTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $laborTotal = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $cartonCount = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $cartonUnitCost = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $sachetCount = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $sachetUnitCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $labelCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $plasticFilmCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $otherPackagingCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $packagingTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $configuredChargesTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $manualChargesAdjustment = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $chargesTotal = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalCost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $costPerInputKg = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $costPerFinishedKg = '0.00';

    /** @var Collection<int, DailyProductionCostChargeLine> */
    #[ORM\OneToMany(targetEntity: DailyProductionCostChargeLine::class, mappedBy: 'dailyProductionCost', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $chargeLines;

    public function __construct()
    {
        $this->productionDate = new \DateTimeImmutable('today');
        $this->chargeLines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getProductionDate(): ?\DateTimeImmutable { return $this->productionDate; }
    public function setProductionDate(?\DateTimeImmutable $productionDate): static { $this->productionDate = $productionDate; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $reference = mb_strtoupper(trim((string) $reference)); $this->reference = $reference !== '' ? $reference : null; return $this; }
    public function getProductName(): string { return $this->productName; }
    public function setProductName(?string $productName): static { $productName = trim((string) $productName); $this->productName = $productName !== '' ? $productName : 'Anchois'; return $this; }
    public function getResponsible(): ?string { return $this->responsible; }
    public function setResponsible(?string $responsible): static { $responsible = trim((string) $responsible); $this->responsible = $responsible !== '' ? $responsible : null; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $notes = trim((string) $notes); $this->notes = $notes !== '' ? $notes : null; return $this; }

    public function getRawQuantityKg(): string { return $this->rawQuantityKg; }
    public function setRawQuantityKg(int|float|string|null $rawQuantityKg): static { $this->rawQuantityKg = $this->decimal($rawQuantityKg, 3); return $this; }
    public function getFinishedProductKg(): string { return $this->finishedProductKg; }
    public function setFinishedProductKg(int|float|string|null $finishedProductKg): static { $this->finishedProductKg = $this->decimal($finishedProductKg, 3); return $this; }
    public function getWasteKg(): string { return $this->wasteKg; }
    public function setWasteKg(int|float|string|null $wasteKg): static { $this->wasteKg = $this->decimal($wasteKg, 3); return $this; }
    public function getLossKg(): string { return $this->lossKg; }
    public function setLossKg(int|float|string|null $lossKg): static { $this->lossKg = $this->decimal($lossKg, 3); return $this; }
    public function getTotalOutputKg(): string { return $this->totalOutputKg; }
    public function setTotalOutputKg(int|float|string|null $totalOutputKg): static { $this->totalOutputKg = $this->decimalSigned($totalOutputKg, 3); return $this; }
    public function getWeightGapKg(): string { return $this->weightGapKg; }
    public function setWeightGapKg(int|float|string|null $weightGapKg): static { $this->weightGapKg = $this->decimalSigned($weightGapKg, 3); return $this; }
    public function getYieldPercent(): string { return $this->yieldPercent; }
    public function setYieldPercent(int|float|string|null $yieldPercent): static { $this->yieldPercent = $this->decimalSigned($yieldPercent); return $this; }

    public function getHourlyWorkers(): int { return $this->hourlyWorkers; }
    public function setHourlyWorkers(int|string|null $hourlyWorkers): static { $this->hourlyWorkers = max(0, (int) $hourlyWorkers); return $this; }
    public function getHourlyHours(): string { return $this->hourlyHours; }
    public function setHourlyHours(int|float|string|null $hourlyHours): static { $this->hourlyHours = $this->decimal($hourlyHours); return $this; }
    public function getHourlyRate(): string { return $this->hourlyRate; }
    public function setHourlyRate(int|float|string|null $hourlyRate): static { $this->hourlyRate = $this->decimal($hourlyRate); return $this; }
    public function getHourlyLaborTotal(): string { return $this->hourlyLaborTotal; }
    public function setHourlyLaborTotal(int|float|string|null $hourlyLaborTotal): static { $this->hourlyLaborTotal = $this->decimal($hourlyLaborTotal); return $this; }

    public function getCleaningKg(): string { return $this->cleaningKg; }
    public function setCleaningKg(int|float|string|null $cleaningKg): static { $this->cleaningKg = $this->decimal($cleaningKg, 3); return $this; }
    public function getCleaningPricePerKg(): string { return $this->cleaningPricePerKg; }
    public function setCleaningPricePerKg(int|float|string|null $cleaningPricePerKg): static { $this->cleaningPricePerKg = $this->decimal($cleaningPricePerKg); return $this; }
    public function getBoxingKg(): string { return $this->boxingKg; }
    public function setBoxingKg(int|float|string|null $boxingKg): static { $this->boxingKg = $this->decimal($boxingKg, 3); return $this; }
    public function getBoxingPricePerKg(): string { return $this->boxingPricePerKg; }
    public function setBoxingPricePerKg(int|float|string|null $boxingPricePerKg): static { $this->boxingPricePerKg = $this->decimal($boxingPricePerKg); return $this; }
    public function getOtherTaskAmount(): string { return $this->otherTaskAmount; }
    public function setOtherTaskAmount(int|float|string|null $otherTaskAmount): static { $this->otherTaskAmount = $this->decimal($otherTaskAmount); return $this; }
    public function getTaskLaborTotal(): string { return $this->taskLaborTotal; }
    public function setTaskLaborTotal(int|float|string|null $taskLaborTotal): static { $this->taskLaborTotal = $this->decimal($taskLaborTotal); return $this; }

    public function getFixedSalaryMonthlyTotal(): string { return $this->fixedSalaryMonthlyTotal; }
    public function setFixedSalaryMonthlyTotal(int|float|string|null $fixedSalaryMonthlyTotal): static { $this->fixedSalaryMonthlyTotal = $this->decimal($fixedSalaryMonthlyTotal); return $this; }
    public function getFixedSalaryWorkingDays(): int { return $this->fixedSalaryWorkingDays; }
    public function setFixedSalaryWorkingDays(int|string|null $fixedSalaryWorkingDays): static { $this->fixedSalaryWorkingDays = max(1, (int) $fixedSalaryWorkingDays); return $this; }
    public function getFixedSalaryDailyTotal(): string { return $this->fixedSalaryDailyTotal; }
    public function setFixedSalaryDailyTotal(int|float|string|null $fixedSalaryDailyTotal): static { $this->fixedSalaryDailyTotal = $this->decimal($fixedSalaryDailyTotal); return $this; }
    public function getLaborTotal(): string { return $this->laborTotal; }
    public function setLaborTotal(int|float|string|null $laborTotal): static { $this->laborTotal = $this->decimal($laborTotal); return $this; }

    public function getCartonCount(): int { return $this->cartonCount; }
    public function setCartonCount(int|string|null $cartonCount): static { $this->cartonCount = max(0, (int) $cartonCount); return $this; }
    public function getCartonUnitCost(): string { return $this->cartonUnitCost; }
    public function setCartonUnitCost(int|float|string|null $cartonUnitCost): static { $this->cartonUnitCost = $this->decimal($cartonUnitCost); return $this; }
    public function getSachetCount(): int { return $this->sachetCount; }
    public function setSachetCount(int|string|null $sachetCount): static { $this->sachetCount = max(0, (int) $sachetCount); return $this; }
    public function getSachetUnitCost(): string { return $this->sachetUnitCost; }
    public function setSachetUnitCost(int|float|string|null $sachetUnitCost): static { $this->sachetUnitCost = $this->decimal($sachetUnitCost); return $this; }
    public function getLabelCost(): string { return $this->labelCost; }
    public function setLabelCost(int|float|string|null $labelCost): static { $this->labelCost = $this->decimal($labelCost); return $this; }
    public function getPlasticFilmCost(): string { return $this->plasticFilmCost; }
    public function setPlasticFilmCost(int|float|string|null $plasticFilmCost): static { $this->plasticFilmCost = $this->decimal($plasticFilmCost); return $this; }
    public function getOtherPackagingCost(): string { return $this->otherPackagingCost; }
    public function setOtherPackagingCost(int|float|string|null $otherPackagingCost): static { $this->otherPackagingCost = $this->decimal($otherPackagingCost); return $this; }
    public function getPackagingTotal(): string { return $this->packagingTotal; }
    public function setPackagingTotal(int|float|string|null $packagingTotal): static { $this->packagingTotal = $this->decimal($packagingTotal); return $this; }

    public function getConfiguredChargesTotal(): string { return $this->configuredChargesTotal; }
    public function setConfiguredChargesTotal(int|float|string|null $configuredChargesTotal): static { $this->configuredChargesTotal = $this->decimal($configuredChargesTotal); return $this; }
    public function getManualChargesAdjustment(): string { return $this->manualChargesAdjustment; }
    public function setManualChargesAdjustment(int|float|string|null $manualChargesAdjustment): static { $this->manualChargesAdjustment = $this->decimal($manualChargesAdjustment); return $this; }
    public function getChargesTotal(): string { return $this->chargesTotal; }
    public function setChargesTotal(int|float|string|null $chargesTotal): static { $this->chargesTotal = $this->decimal($chargesTotal); return $this; }
    public function getTotalCost(): string { return $this->totalCost; }
    public function setTotalCost(int|float|string|null $totalCost): static { $this->totalCost = $this->decimal($totalCost); return $this; }
    public function getCostPerInputKg(): string { return $this->costPerInputKg; }
    public function setCostPerInputKg(int|float|string|null $costPerInputKg): static { $this->costPerInputKg = $this->decimal($costPerInputKg); return $this; }
    public function getCostPerFinishedKg(): string { return $this->costPerFinishedKg; }
    public function setCostPerFinishedKg(int|float|string|null $costPerFinishedKg): static { $this->costPerFinishedKg = $this->decimal($costPerFinishedKg); return $this; }

    /** @return Collection<int, DailyProductionCostChargeLine> */
    public function getChargeLines(): Collection { return $this->chargeLines; }

    public function addChargeLine(DailyProductionCostChargeLine $chargeLine): static
    {
        if (!$this->chargeLines->contains($chargeLine)) {
            $this->chargeLines->add($chargeLine);
            $chargeLine->setDailyProductionCost($this);
        }

        return $this;
    }

    public function removeChargeLine(DailyProductionCostChargeLine $chargeLine): static
    {
        if ($this->chargeLines->removeElement($chargeLine) && $chargeLine->getDailyProductionCost() === $this) {
            $chargeLine->setDailyProductionCost(null);
        }

        return $this;
    }

    /** @return list<string> */
    public function getAlertMessages(): array
    {
        $alerts = [];
        if ((float) $this->rawQuantityKg > 0 && abs((float) $this->weightGapKg) > 0.1) {
            $alerts[] = 'Le total PF + dechets + pertes ne correspond pas a la quantite sortie.';
        }
        if ((float) $this->finishedProductKg <= 0 && (float) $this->rawQuantityKg > 0) {
            $alerts[] = 'Le cout par kg PF ne peut pas etre calcule sans produit fini.';
        }
        if ((float) $this->yieldPercent > 100) {
            $alerts[] = 'Rendement impossible : le produit fini depasse la matiere sortie.';
        }
        if ((float) $this->yieldPercent > 0 && (float) $this->yieldPercent < 35) {
            $alerts[] = 'Rendement faible pour un filet anchois standard.';
        }

        return $alerts;
    }

    public function getProductionDiagnosticClass(): string
    {
        if ((float) $this->rawQuantityKg <= 0 || (float) $this->totalOutputKg <= 0) {
            return 'secondary';
        }

        if (abs((float) $this->weightGapKg) > 0.1 || (float) $this->yieldPercent < 35 || (float) $this->yieldPercent > 60) {
            return 'warning';
        }

        if ((float) $this->yieldPercent >= 35 && (float) $this->yieldPercent <= 45) {
            return 'success';
        }

        return 'info';
    }

    public function floatValue(string $property): float
    {
        return property_exists($this, $property) ? (float) $this->{$property} : 0.0;
    }

    private function decimal(int|float|string|null $value, int $scale = 2): string
    {
        return number_format(max(0, $this->number($value)), $scale, '.', '');
    }

    private function decimalSigned(int|float|string|null $value, int $scale = 2): string
    {
        return number_format($this->number($value), $scale, '.', '');
    }

    private function number(int|float|string|null $value): float
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
