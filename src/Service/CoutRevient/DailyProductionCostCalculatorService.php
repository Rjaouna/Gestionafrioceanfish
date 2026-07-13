<?php

namespace App\Service\CoutRevient;

use App\Entity\DailyProductionCost;
use App\Entity\DailyProductionCostChargeLine;

final readonly class DailyProductionCostCalculatorService
{
    private const CLEANING_RATE_AMOUNT = 25.0;
    private const CLEANING_RATE_KG = 30.0;
    private const CLEANING_CRATE_KG = 10.0;
    private const BOXING_RATE_PER_KG = 2.0;

    /** @return array<string, mixed> */
    public function calculate(DailyProductionCost $cost): array
    {
        $raw = $this->num($cost->getRawQuantityKg());
        $finished = $this->num($cost->getFinishedProductKg());
        $waste = $this->num($cost->getWasteKg());
        $loss = $this->num($cost->getLossKg());
        $totalOutput = $finished + $waste + $loss;
        $gap = $totalOutput - $raw;
        $yield = $raw > 0 ? ($finished / $raw) * 100 : 0.0;

        $hourly = $cost->getHourlyWorkers() * $this->num($cost->getHourlyHours()) * $this->num($cost->getHourlyRate());
        $cleaningTotal = $this->calculateCleaningLabor($raw);
        $boxingTotal = $this->calculateBoxingLabor($finished);
        $task = $cleaningTotal + $boxingTotal + $this->num($cost->getOtherTaskAmount());
        $fixed = $this->num($cost->getFixedSalaryMonthlyTotal()) / max(1, $cost->getFixedSalaryWorkingDays());
        $labor = $hourly + $task + $fixed;

        $cartons = $cost->getCartonCount() * $this->num($cost->getCartonUnitCost());
        $sachets = $cost->getSachetCount() * $this->num($cost->getSachetUnitCost());
        $packaging = $cartons + $sachets + $this->num($cost->getLabelCost()) + $this->num($cost->getPlasticFilmCost()) + $this->num($cost->getOtherPackagingCost());

        $configuredCharges = 0.0;
        foreach ($cost->getChargeLines() as $line) {
            $line->recalculate();
            $configuredCharges += $this->num($line->getTotalAmount());
        }

        $charges = $configuredCharges + $this->num($cost->getManualChargesAdjustment());
        $total = $labor + $packaging + $charges;

        $cost
            ->setTotalOutputKg($totalOutput)
            ->setWeightGapKg($gap)
            ->setYieldPercent($yield)
            ->setHourlyLaborTotal($hourly)
            ->setCleaningKg($raw)
            ->setCleaningPricePerKg(self::CLEANING_RATE_AMOUNT / self::CLEANING_RATE_KG)
            ->setBoxingKg($finished)
            ->setBoxingPricePerKg(self::BOXING_RATE_PER_KG)
            ->setTaskLaborTotal($task)
            ->setFixedSalaryDailyTotal($fixed)
            ->setLaborTotal($labor)
            ->setPackagingTotal($packaging)
            ->setConfiguredChargesTotal($configuredCharges)
            ->setChargesTotal($charges)
            ->setTotalCost($total)
            ->setCostPerInputKg($raw > 0 ? $total / $raw : 0)
            ->setCostPerFinishedKg($finished > 0 ? $total / $finished : 0);

        return $this->summary($cost, $cartons, $sachets, $cleaningTotal, $boxingTotal);
    }

    /** @param array<string, mixed> $payload */
    public function calculatePayload(array $payload): array
    {
        $cost = (new DailyProductionCost())
            ->setRawQuantityKg($payload['rawQuantityKg'] ?? 0)
            ->setFinishedProductKg($payload['finishedProductKg'] ?? 0)
            ->setWasteKg($payload['wasteKg'] ?? 0)
            ->setLossKg($payload['lossKg'] ?? 0)
            ->setHourlyWorkers($payload['hourlyWorkers'] ?? 0)
            ->setHourlyHours($payload['hourlyHours'] ?? 0)
            ->setHourlyRate($payload['hourlyRate'] ?? 0)
            ->setOtherTaskAmount($payload['otherTaskAmount'] ?? 0)
            ->setFixedSalaryMonthlyTotal($payload['fixedSalaryMonthlyTotal'] ?? 0)
            ->setFixedSalaryWorkingDays($payload['fixedSalaryWorkingDays'] ?? 26)
            ->setCartonCount($payload['cartonCount'] ?? 0)
            ->setCartonUnitCost($payload['cartonUnitCost'] ?? 0)
            ->setSachetCount($payload['sachetCount'] ?? 0)
            ->setSachetUnitCost($payload['sachetUnitCost'] ?? 0)
            ->setLabelCost($payload['labelCost'] ?? 0)
            ->setPlasticFilmCost($payload['plasticFilmCost'] ?? 0)
            ->setOtherPackagingCost($payload['otherPackagingCost'] ?? 0)
            ->setManualChargesAdjustment($payload['manualChargesAdjustment'] ?? 0);

        $this->hydrateChargeLines($cost, is_array($payload['chargeLines'] ?? null) ? $payload['chargeLines'] : []);

        return $this->calculate($cost);
    }

    /** @return array<string, mixed> */
    public function chartData(DailyProductionCost $cost): array
    {
        $production = [
            ['label' => 'Produit fini', 'value' => (float) $cost->getFinishedProductKg(), 'color' => '#10b981'],
            ['label' => 'Dechets', 'value' => (float) $cost->getWasteKg(), 'color' => '#f59e0b'],
            ['label' => 'Pertes', 'value' => (float) $cost->getLossKg(), 'color' => '#ef4444'],
        ];
        $costs = [
            ['label' => 'Main oeuvre', 'value' => (float) $cost->getLaborTotal(), 'color' => '#2563eb'],
            ['label' => 'Emballage', 'value' => (float) $cost->getPackagingTotal(), 'color' => '#14b8a6'],
            ['label' => 'Charges', 'value' => (float) $cost->getChargesTotal(), 'color' => '#8b5cf6'],
        ];

        return [
            'production' => $production,
            'costs' => $costs,
            'production_total' => array_sum(array_column($production, 'value')),
            'costs_total' => array_sum(array_column($costs, 'value')),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(DailyProductionCost $cost, float $cartons, float $sachets, float $cleaningTotal, float $boxingTotal): array
    {
        return [
            'totalOutputKg' => (float) $cost->getTotalOutputKg(),
            'weightGapKg' => (float) $cost->getWeightGapKg(),
            'yieldPercent' => (float) $cost->getYieldPercent(),
            'hourlyLaborTotal' => (float) $cost->getHourlyLaborTotal(),
            'cleaningKgAuto' => (float) $cost->getRawQuantityKg(),
            'cleaningCratesAuto' => $this->calculateCleaningCrates((float) $cost->getRawQuantityKg()),
            'cleaningTotal' => $cleaningTotal,
            'boxingKgAuto' => (float) $cost->getFinishedProductKg(),
            'boxingTotal' => $boxingTotal,
            'taskLaborTotal' => (float) $cost->getTaskLaborTotal(),
            'fixedSalaryDailyTotal' => (float) $cost->getFixedSalaryDailyTotal(),
            'laborTotal' => (float) $cost->getLaborTotal(),
            'cartonsTotal' => $cartons,
            'sachetsTotal' => $sachets,
            'packagingTotal' => (float) $cost->getPackagingTotal(),
            'configuredChargesTotal' => (float) $cost->getConfiguredChargesTotal(),
            'chargesTotal' => (float) $cost->getChargesTotal(),
            'totalCost' => (float) $cost->getTotalCost(),
            'costPerInputKg' => (float) $cost->getCostPerInputKg(),
            'costPerFinishedKg' => (float) $cost->getCostPerFinishedKg(),
            'alerts' => $cost->getAlertMessages(),
        ];
    }

    /** @param array<int|string, mixed> $rows */
    private function hydrateChargeLines(DailyProductionCost $cost, array $rows): void
    {
        $sortOrder = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['remove'])) {
                continue;
            }

            $line = (new DailyProductionCostChargeLine())
                ->setName((string) ($row['name'] ?? ''))
                ->setCategory((string) ($row['category'] ?? ''))
                ->setCalculationUnit((string) ($row['calculationUnit'] ?? ''))
                ->setUnitCost($row['unitCost'] ?? 0)
                ->setQuantity($row['quantity'] ?? 0)
                ->setSortOrder(++$sortOrder)
                ->recalculate();

            if ($line->getName() !== '' && (float) $line->getQuantity() > 0) {
                $cost->addChargeLine($line);
            }
        }
    }

    private function num(int|float|string|null $value): float
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function calculateCleaningLabor(float $raw): float
    {
        return $raw > 0 ? ($raw / self::CLEANING_RATE_KG) * self::CLEANING_RATE_AMOUNT : 0.0;
    }

    private function calculateBoxingLabor(float $finished): float
    {
        return $finished > 0 ? $finished * self::BOXING_RATE_PER_KG : 0.0;
    }

    private function calculateCleaningCrates(float $raw): float
    {
        return $raw > 0 ? $raw / self::CLEANING_CRATE_KG : 0.0;
    }
}
