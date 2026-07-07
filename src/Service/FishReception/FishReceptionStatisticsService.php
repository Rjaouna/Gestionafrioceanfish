<?php

namespace App\Service\FishReception;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\FishReception;
use App\Entity\FishReceptionStorageMovement;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\FishReceptionRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class FishReceptionStatisticsService
{
    public function __construct(
        private FishReceptionRepository $receptionRepository,
        private CoutRevientChargeConfigRepository $chargeConfigRepository,
        private FishReceptionPermissionService $permission,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function build(User $actor, array $filters = []): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $filters = $this->normalizeFilters($filters);
        $from = new \DateTimeImmutable($filters['dateFrom'].' 00:00:00');
        $to = new \DateTimeImmutable($filters['dateTo'].' 23:59:59');
        $days = $this->emptyDays($from, $to);
        $receptions = $this->receptionRepository->findStatisticsBetween($from, $to);

        foreach ($receptions as $reception) {
            $this->addReceptionActivity($days, $reception);
        }

        $charges = $this->estimateCharges($days, $this->chargeConfigRepository->findActive());
        $totals = $this->totals($days);

        return [
            'filters' => $filters,
            'period' => [
                'from' => $from,
                'to' => $to,
                'days' => count($days),
            ],
            'days' => array_values($days),
            'totals' => $totals,
            'kpis' => $this->kpis($totals),
            'charts' => [
                'finished' => $this->barChart($days, 'finished', ' kg', 'success'),
                'waste' => $this->barChart($days, 'waste', ' kg', 'warning'),
                'loss' => $this->barChart($days, 'total_loss', ' kg', 'danger'),
                'charges' => $this->barChart($days, 'charges', ' dh', 'primary', 0),
                'rendement' => $this->barChart($days, 'yield_rate', ' %', 'info', 1),
                'cost_per_kg' => $this->barChart($days, 'cost_per_finished_kg', ' dh/kg', 'secondary', 2),
                'hours' => $this->barChart($days, 'hours', ' h', 'primary', 1),
                'packaged_net' => $this->barChart($days, 'packaged_net', ' kg', 'success'),
                'charges_by_category' => $this->pieChart($charges['by_category']),
            ],
            'charge_lines' => $charges['lines'],
            'charges_by_category' => array_values($charges['by_category']),
            'activity_lines' => $this->activityLines($days),
            'warnings' => $this->warnings($days, $charges, $totals),
            'assumptions' => [
                'Les charges par kg utilisent les kg sortis vers traitement. Si un jour contient seulement emballage, le poids emballe sert de base.',
                'Les charges par heure utilisent les heures saisies sur le workflow. Sans duree exploitable pour une sortie traitement, le systeme retient 1 h par sortie.',
                'Les charges mensuelles sont proratees sur 30 jours et appliquees uniquement aux jours avec activite.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{dateFrom: string, dateTo: string}
     */
    private function normalizeFilters(array $filters): array
    {
        $defaultTo = (new \DateTimeImmutable('today'))->modify('-1 day');
        $to = $this->parseDate((string) ($filters['dateTo'] ?? ''), $defaultTo);
        $from = $to->modify('-5 days');

        return [
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function emptyDays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        for ($day = $from->setTime(0, 0); $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $days[$key] = [
                'key' => $key,
                'date' => $day,
                'label' => $day->format('d/m/Y'),
                'short_label' => $day->format('d/m'),
                'prepared' => 0.0,
                'finished' => 0.0,
                'waste' => 0.0,
                'treatment_loss' => 0.0,
                'packaging_loss' => 0.0,
                'total_loss' => 0.0,
                'packaged' => 0.0,
                'packaged_net' => 0.0,
                'hours' => 0.0,
                'lots' => [],
                'activity_count' => 0,
                'charges' => 0.0,
                'cost_per_finished_kg' => 0.0,
                'yield_rate' => 0.0,
                'reject_rate' => 0.0,
                'lines' => [],
            ];
        }

        return $days;
    }

    /** @param array<string, array<string, mixed>> $days */
    private function addReceptionActivity(array &$days, FishReception $reception): void
    {
        $rows = $reception->getTreatmentExitRows();
        $totalExitKg = array_sum(array_map(
            static fn (array $row): float => $row['movement']->getAbsoluteQuantityKgValue(),
            $rows,
        ));
        $addedTreatment = false;

        foreach ($rows as $row) {
            $movement = $row['movement'];
            \assert($movement instanceof FishReceptionStorageMovement);
            $date = $movement->getMovementDate();
            if (!$date instanceof \DateTimeImmutable || !isset($days[$date->format('Y-m-d')])) {
                continue;
            }

            $prepared = $movement->getAbsoluteQuantityKgValue();
            $ratio = $totalExitKg > 0.0 ? $prepared / $totalExitKg : 0.0;
            $finished = $row['product'] ?? ($reception->getQuantiteCongeleeValue() * $ratio);
            $waste = $row['waste'] ?? ($reception->getPoidsDechetsTraitementValue() * $ratio);
            $loss = $row['loss'] ?? ($reception->getPoidsPertesTraitementValue() * $ratio);
            [$hours, $hoursBasis] = $this->treatmentHours($reception, $date, count($rows));

            $this->addDayLine($days[$date->format('Y-m-d')], $reception, [
                'type' => 'traitement',
                'label' => 'Sortie traitement',
                'prepared' => $prepared,
                'finished' => $finished,
                'waste' => $waste,
                'loss' => $loss,
                'packaging_loss' => 0.0,
                'packaged' => 0.0,
                'packaged_net' => 0.0,
                'hours' => $hours,
                'hours_basis' => $hoursBasis,
                'note' => $movement->getNote(),
            ]);
            $addedTreatment = true;
        }

        if (!$addedTreatment && $reception->getDateDebutTraitement() instanceof \DateTimeImmutable) {
            $date = $reception->getDateDebutTraitement();
            if (isset($days[$date->format('Y-m-d')]) && $reception->getQuantiteTotalePrepareeValue() > 0.0) {
                [$hours, $hoursBasis] = $this->treatmentHours($reception, $date, 1);
                $this->addDayLine($days[$date->format('Y-m-d')], $reception, [
                    'type' => 'traitement',
                    'label' => 'Traitement global',
                    'prepared' => $reception->getQuantiteTotalePrepareeValue(),
                    'finished' => $reception->getQuantiteCongeleeValue(),
                    'waste' => $reception->getPoidsDechetsTraitementValue(),
                    'loss' => $reception->getPoidsPertesTraitementValue(),
                    'packaging_loss' => 0.0,
                    'packaged' => 0.0,
                    'packaged_net' => 0.0,
                    'hours' => $hours,
                    'hours_basis' => $hoursBasis,
                    'note' => 'Bilan traitement de la fiche.',
                ]);
            }
        }

        $this->addPackagingActivity($days, $reception);
        $this->refreshDerivedDayValues($days);
    }

    /** @param array<string, array<string, mixed>> $days */
    private function addPackagingActivity(array &$days, FishReception $reception): void
    {
        $date = $reception->getDateConditionnement();
        if (!$date instanceof \DateTimeImmutable || !isset($days[$date->format('Y-m-d')])) {
            return;
        }

        $packaged = $reception->getQuantiteConditionneeValue();
        $net = $reception->getPoidsNetValue();
        $returned = $reception->getQuantiteRemiseEnChambreValue();
        $netReference = $net > 0.0 ? $net : $returned;
        $waste = $reception->getPoidsDechetsEmballageValue();
        $loss = $reception->getPoidsPertesEmballageValue();
        $explicitLoss = $waste + $loss;
        $computedLoss = $packaged > 0.0 && $netReference > 0.0
            ? max(0.0, $packaged - $netReference - $explicitLoss)
            : 0.0;
        $packagingLoss = $explicitLoss + $computedLoss;
        $hours = $reception->getDureeConditionnementHeuresValue();

        if ($packaged <= 0.0 && $netReference <= 0.0 && $waste <= 0.0 && $loss <= 0.0) {
            return;
        }

        $this->addDayLine($days[$date->format('Y-m-d')], $reception, [
            'type' => 'emballage',
            'label' => 'Emballage + retour chambre',
            'prepared' => 0.0,
            'finished' => 0.0,
            'waste' => 0.0,
            'loss' => 0.0,
            'packaging_loss' => $packagingLoss,
            'packaged' => $packaged,
            'packaged_net' => $netReference,
            'hours' => $hours,
            'hours_basis' => $hours > 0.0 ? 'Duree emballage saisie' : 'Aucune duree emballage exploitable',
            'note' => trim(sprintf('Poids net %.3f kg, dechets/pertes emballage %.3f kg.', $netReference, $packagingLoss)),
        ]);
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $payload
     */
    private function addDayLine(array &$day, FishReception $reception, array $payload): void
    {
        $prepared = max(0.0, (float) ($payload['prepared'] ?? 0));
        $finished = max(0.0, (float) ($payload['finished'] ?? 0));
        $waste = max(0.0, (float) ($payload['waste'] ?? 0));
        $loss = max(0.0, (float) ($payload['loss'] ?? 0));
        $packagingLoss = max(0.0, (float) ($payload['packaging_loss'] ?? 0));
        $packaged = max(0.0, (float) ($payload['packaged'] ?? 0));
        $packagedNet = max(0.0, (float) ($payload['packaged_net'] ?? 0));
        $hours = max(0.0, (float) ($payload['hours'] ?? 0));

        $day['prepared'] += $prepared;
        $day['finished'] += $finished;
        $day['waste'] += $waste;
        $day['treatment_loss'] += $loss;
        $day['packaging_loss'] += $packagingLoss;
        $day['packaged'] += $packaged;
        $day['packaged_net'] += $packagedNet;
        $day['hours'] += $hours;
        $day['lots'][(int) $reception->getId()] = true;
        $day['activity_count']++;
        $day['lines'][] = [
            'date' => $day['date'],
            'type' => (string) ($payload['type'] ?? 'activite'),
            'label' => (string) ($payload['label'] ?? 'Activite'),
            'reception' => $reception,
            'reference' => (string) $reception->getNumeroReception(),
            'product' => trim((string) $reception->getEspecePoisson()),
            'supplier' => trim((string) $reception->getFournisseur()),
            'prepared' => $prepared,
            'finished' => $finished,
            'waste' => $waste,
            'loss' => $loss,
            'packaging_loss' => $packagingLoss,
            'packaged' => $packaged,
            'packaged_net' => $packagedNet,
            'hours' => $hours,
            'hours_basis' => (string) ($payload['hours_basis'] ?? ''),
            'note' => (string) ($payload['note'] ?? ''),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $days
     */
    private function refreshDerivedDayValues(array &$days): void
    {
        foreach ($days as &$day) {
            $day['total_loss'] = (float) $day['treatment_loss'] + (float) $day['packaging_loss'];
            $day['yield_rate'] = (float) $day['prepared'] > 0.0 ? ((float) $day['finished'] / (float) $day['prepared']) * 100.0 : 0.0;
            $rejects = (float) $day['waste'] + (float) $day['total_loss'];
            $day['reject_rate'] = (float) $day['prepared'] > 0.0 ? ($rejects / (float) $day['prepared']) * 100.0 : 0.0;
            $day['cost_per_finished_kg'] = (float) $day['finished'] > 0.0 ? (float) $day['charges'] / (float) $day['finished'] : 0.0;
        }
        unset($day);
    }

    /**
     * @param array<string, array<string, mixed>> $days
     * @param list<CoutRevientChargeConfig>      $configs
     *
     * @return array{lines: list<array<string, mixed>>, by_category: array<string, array<string, mixed>>, total: float, count: int}
     */
    private function estimateCharges(array &$days, array $configs): array
    {
        $linesByConfig = [];
        $byCategory = [];
        $total = 0.0;

        foreach ($configs as $config) {
            $configId = (int) ($config->getId() ?? 0);
            $linesByConfig[$configId] = [
                'name' => (string) $config->getName(),
                'category' => $config->getCategory(),
                'category_label' => $config->getCategoryLabel(),
                'unit_label' => $config->getCalculationUnitLabel(),
                'unit_short' => $this->chargeQuantityUnit($config),
                'unit_cost' => $this->displayUnitCost($config),
                'quantity' => 0.0,
                'total' => 0.0,
                'basis' => $this->chargeBasis($config),
                'factory_unit' => $config->getFactoryUnit()?->getDisplayName(),
            ];
        }

        foreach ($days as &$day) {
            $isActiveDay = (float) $day['prepared'] > 0.0
                || (float) $day['packaged'] > 0.0
                || (float) $day['hours'] > 0.0
                || (int) $day['activity_count'] > 0;
            $kgReference = max((float) $day['prepared'], (float) $day['packaged']);
            $lotReference = count((array) $day['lots']);

            foreach ($configs as $config) {
                $unitCost = (float) $config->getUnitCost();
                [$quantity, $appliedUnitCost] = match ($config->getCalculationUnit()) {
                    CoutRevientChargeConfig::UNIT_MONTH => [$isActiveDay ? 1.0 : 0.0, $unitCost / 30.0],
                    CoutRevientChargeConfig::UNIT_DAY => [$isActiveDay ? 1.0 : 0.0, $unitCost],
                    CoutRevientChargeConfig::UNIT_HOUR => [(float) $day['hours'], $unitCost],
                    CoutRevientChargeConfig::UNIT_KG => [$kgReference, $unitCost],
                    CoutRevientChargeConfig::UNIT_LOT => [(float) $lotReference, $unitCost],
                    default => [$isActiveDay ? 1.0 : 0.0, $unitCost],
                };

                $lineTotal = $quantity * $appliedUnitCost;
                if ($lineTotal <= 0.0 && $quantity <= 0.0) {
                    continue;
                }

                $configId = (int) ($config->getId() ?? 0);
                $linesByConfig[$configId]['quantity'] += $quantity;
                $linesByConfig[$configId]['total'] += $lineTotal;
                $day['charges'] += $lineTotal;
                $total += $lineTotal;

                $category = $config->getCategory();
                $byCategory[$category] ??= [
                    'category' => $category,
                    'label' => $config->getCategoryLabel(),
                    'total' => 0.0,
                    'count' => 0,
                ];
                $byCategory[$category]['total'] += $lineTotal;
                $byCategory[$category]['count']++;
            }
        }
        unset($day);

        $this->refreshDerivedDayValues($days);
        uasort($byCategory, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'lines' => array_values(array_filter($linesByConfig, static fn (array $line): bool => (float) $line['total'] > 0.0 || (float) $line['quantity'] > 0.0)),
            'by_category' => $byCategory,
            'total' => $total,
            'count' => count($configs),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $days
     *
     * @return array<string, float|int>
     */
    private function totals(array $days): array
    {
        $totals = [
            'prepared' => 0.0,
            'finished' => 0.0,
            'waste' => 0.0,
            'treatment_loss' => 0.0,
            'packaging_loss' => 0.0,
            'total_loss' => 0.0,
            'packaged' => 0.0,
            'packaged_net' => 0.0,
            'hours' => 0.0,
            'charges' => 0.0,
            'active_days' => 0,
            'activity_count' => 0,
            'lots' => 0,
            'yield_rate' => 0.0,
            'reject_rate' => 0.0,
            'cost_per_finished_kg' => 0.0,
        ];
        $lotIds = [];

        foreach ($days as $day) {
            foreach (['prepared', 'finished', 'waste', 'treatment_loss', 'packaging_loss', 'total_loss', 'packaged', 'packaged_net', 'hours', 'charges'] as $key) {
                $totals[$key] += (float) $day[$key];
            }
            $totals['activity_count'] += (int) $day['activity_count'];
            if ((int) $day['activity_count'] > 0) {
                $totals['active_days']++;
            }
            foreach ((array) $day['lots'] as $id => $value) {
                if ($value) {
                    $lotIds[(int) $id] = true;
                }
            }
        }

        $rejects = (float) $totals['waste'] + (float) $totals['total_loss'];
        $totals['lots'] = count($lotIds);
        $totals['yield_rate'] = (float) $totals['prepared'] > 0.0 ? ((float) $totals['finished'] / (float) $totals['prepared']) * 100.0 : 0.0;
        $totals['reject_rate'] = (float) $totals['prepared'] > 0.0 ? ($rejects / (float) $totals['prepared']) * 100.0 : 0.0;
        $totals['cost_per_finished_kg'] = (float) $totals['finished'] > 0.0 ? (float) $totals['charges'] / (float) $totals['finished'] : 0.0;

        return $totals;
    }

    /** @return list<array<string, string>> */
    private function kpis(array $totals): array
    {
        return [
            ['label' => 'Produit fini', 'display' => $this->kg((float) $totals['finished']), 'hint' => 'PF tunnel sur 6 jours', 'icon' => 'bi-snow', 'tone' => 'success'],
            ['label' => 'Dechets', 'display' => $this->kg((float) $totals['waste']), 'hint' => 'dechets traitement', 'icon' => 'bi-recycle', 'tone' => 'warning'],
            ['label' => 'Pertes', 'display' => $this->kg((float) $totals['total_loss']), 'hint' => 'traitement + emballage', 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger'],
            ['label' => 'Rendement', 'display' => $this->number((float) $totals['yield_rate'], 1).' %', 'hint' => 'PF / kg traites', 'icon' => 'bi-percent', 'tone' => 'info'],
            ['label' => 'Charges', 'display' => $this->money((float) $totals['charges']), 'hint' => 'estimation charges actives', 'icon' => 'bi-cash-stack', 'tone' => 'primary'],
            ['label' => 'Cout PF', 'display' => $this->number((float) $totals['cost_per_finished_kg'], 2).' dh/kg', 'hint' => 'charges / PF', 'icon' => 'bi-calculator', 'tone' => 'secondary'],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $days
     *
     * @return list<array<string, mixed>>
     */
    private function activityLines(array $days): array
    {
        $lines = [];
        foreach ($days as $day) {
            foreach ((array) $day['lines'] as $line) {
                $lines[] = $line;
            }
        }

        usort($lines, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $lines;
    }

    /**
     * @param array<string, array<string, mixed>> $days
     *
     * @return list<array<string, mixed>>
     */
    private function barChart(array $days, string $key, string $suffix, string $tone, int $decimals = 0): array
    {
        $max = 0.0;
        foreach ($days as $day) {
            $max = max($max, (float) $day[$key]);
        }

        $points = [];
        foreach ($days as $day) {
            $value = (float) $day[$key];
            $points[] = [
                'label' => (string) $day['label'],
                'short_label' => (string) $day['short_label'],
                'value' => $value,
                'display' => $this->number($value, $decimals).$suffix,
                'percent' => $max > 0.0 ? min(100.0, ($value / $max) * 100.0) : 0.0,
                'tone' => $tone,
            ];
        }

        return $points;
    }

    /**
     * @param array<string, array<string, mixed>> $categories
     *
     * @return list<array<string, mixed>>
     */
    private function pieChart(array $categories): array
    {
        $total = array_sum(array_map(static fn (array $category): float => (float) $category['total'], $categories));
        $offset = 0.0;
        $points = [];

        foreach ($categories as $category) {
            $share = $total > 0.0 ? ((float) $category['total'] / $total) * 100.0 : 0.0;
            $points[] = [
                'label' => (string) $category['label'],
                'value' => (float) $category['total'],
                'display' => $this->money((float) $category['total']),
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $points;
    }

    /** @return array{0: float, 1: string} */
    private function treatmentHours(FishReception $reception, \DateTimeImmutable $date, int $rowsCount): array
    {
        $duration = $reception->getDureeTunnelHeuresValue();
        if ($rowsCount <= 1 && $duration > 0.0) {
            return [$duration, 'Duree tunnel saisie'];
        }

        if ($duration > 0.0 && (
            $this->sameDay($reception->getDateEntreeTunnel(), $date)
            || $this->sameDay($reception->getDateSortieTunnel(), $date)
        )) {
            return [$duration, 'Duree tunnel saisie'];
        }

        return [1.0, 'Hypothese 1 h par sortie traitement'];
    }

    private function chargeBasis(CoutRevientChargeConfig $config): string
    {
        return match ($config->getCalculationUnit()) {
            CoutRevientChargeConfig::UNIT_MONTH => 'Prorata mois / jours actifs',
            CoutRevientChargeConfig::UNIT_DAY => 'Jours avec activite',
            CoutRevientChargeConfig::UNIT_HOUR => 'Heures workflow production',
            CoutRevientChargeConfig::UNIT_KG => 'Kg traites ou emballes',
            CoutRevientChargeConfig::UNIT_LOT => 'Lots actifs par jour',
            default => 'Forfait par jour actif',
        };
    }

    private function chargeQuantityUnit(CoutRevientChargeConfig $config): string
    {
        return match ($config->getCalculationUnit()) {
            CoutRevientChargeConfig::UNIT_MONTH, CoutRevientChargeConfig::UNIT_DAY => 'jour',
            default => $config->getCalculationUnitShortLabel(),
        };
    }

    private function displayUnitCost(CoutRevientChargeConfig $config): float
    {
        $unitCost = (float) $config->getUnitCost();

        return $config->getCalculationUnit() === CoutRevientChargeConfig::UNIT_MONTH ? $unitCost / 30.0 : $unitCost;
    }

    /**
     * @param array<string, array<string, mixed>> $days
     * @param array<string, mixed>                $charges
     *
     * @return list<array{tone: string, text: string}>
     */
    private function warnings(array $days, array $charges, array $totals): array
    {
        $warnings = [];
        if ((int) $totals['activity_count'] === 0) {
            $warnings[] = ['tone' => 'info', 'text' => 'Aucune sortie traitement ou emballage detectee sur les 6 jours affiches.'];
        }
        if ((int) $charges['count'] === 0) {
            $warnings[] = ['tone' => 'warning', 'text' => 'Aucune charge active dans Charges production. Les graphes de cout restent a zero.'];
        }

        return $warnings;
    }

    private function sameDay(?\DateTimeImmutable $date, \DateTimeImmutable $reference): bool
    {
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $reference->format('Y-m-d');
    }

    private function parseDate(string $value, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function kg(float $value): string
    {
        return $this->number($value, 0).' kg';
    }

    private function money(float $value): string
    {
        return $this->number($value, 0).' dh';
    }

    private function number(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
