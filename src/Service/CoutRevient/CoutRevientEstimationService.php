<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevient;
use App\Entity\CoutRevientChargeConfig;
use App\Entity\FishReception;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\CoutRevientRepository;
use App\Repository\FishReceptionRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CoutRevientEstimationService
{
    public function __construct(
        private FishReceptionRepository $receptionRepository,
        private CoutRevientRepository $coutRevientRepository,
        private CoutRevientChargeConfigRepository $chargeConfigRepository,
        private CoutRevientPermissionService $permission,
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
        $receptions = $this->receptionRepository->findActivityBetween($from, $to);
        $costLots = $this->coutRevientRepository->findForExport([
            'dateFrom' => $filters['dateFrom'],
            'dateTo' => $filters['dateTo'],
            'sort' => 'date',
            'direction' => 'desc',
        ]);

        $events = $this->buildEvents($receptions, $costLots, $from, $to);
        $workflow = $this->workflowSummary($events);
        $operation = $this->operationBasis($receptions, $costLots, $events, $workflow, $from, $to);
        $charges = $this->estimateCharges($this->chargeConfigRepository->findActive(), $operation);
        $costLotStats = $this->costLotStats($costLots);

        return [
            'filters' => $filters,
            'period' => [
                'from' => $from,
                'to' => $to,
                'days' => $operation['period_days'],
            ],
            'stats' => [
                'events' => count($events),
                'receptions' => count($receptions),
                'cout_lots' => count($costLots),
                'kg_reference' => $operation['kg_reference'],
                'known_hours' => $operation['known_hours'],
                'estimated_charges' => $charges['total'],
                'real_cost_total' => $costLotStats['total_cost'],
                'estimated_cost_kg' => $operation['kg_reference'] > 0 ? $charges['total'] / $operation['kg_reference'] : 0.0,
                'real_cost_kg' => $costLotStats['finished_weight'] > 0 ? $costLotStats['total_cost'] / $costLotStats['finished_weight'] : 0.0,
            ],
            'workflow' => $workflow,
            'events' => $events,
            'charges' => $charges,
            'operation' => $operation,
            'cost_lots' => $costLots,
            'cost_lot_stats' => $costLotStats,
            'warnings' => $this->warnings($charges, $operation, $costLots),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{dateFrom: string, dateTo: string}
     */
    public function normalizeFilters(array $filters): array
    {
        $today = new \DateTimeImmutable('today');
        $from = $this->parseDate((string) ($filters['dateFrom'] ?? ''), $today->modify('first day of this month'));
        $to = $this->parseDate((string) ($filters['dateTo'] ?? ''), $today);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
        ];
    }

    /**
     * @param list<FishReception> $receptions
     * @param list<CoutRevient>   $costLots
     *
     * @return list<array<string, mixed>>
     */
    private function buildEvents(array $receptions, array $costLots, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $events = [];

        foreach ($receptions as $reception) {
            $this->addEvent($events, $reception->getCreatedAt(), $from, $to, [
                'stage' => 'creation',
                'stage_label' => 'Creation',
                'title' => 'Fiche réception creee',
                'badge' => 'text-bg-secondary',
                'icon' => 'bi-file-earmark-plus',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => trim((string) $reception->getFournisseur().' - '.(string) $reception->getEspecePoisson(), ' -'),
                'actor' => $this->userName($reception->getCreatedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->combine($reception->getDateReception(), $reception->getHeureDebutReception()), $from, $to, [
                'stage' => 'reception',
                'stage_label' => 'Reception',
                'quantity_key' => 'received',
                'quantity' => $reception->getQuantiteReceptionneeValue(),
                'unit' => 'kg',
                'title' => 'Réception matière première',
                'badge' => 'text-bg-primary',
                'icon' => 'bi-clipboard2-check',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => sprintf('%s - %s - %s caisse(s)', (string) $reception->getFournisseur(), (string) $reception->getEspecePoisson(), $reception->getNombreCaissesReception()),
                'actor' => $this->userName($reception->getCreatedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $reception->getReceivedAt(), $from, $to, [
                'stage' => 'validation',
                'stage_label' => 'Validation',
                'title' => 'Reception validée',
                'badge' => 'text-bg-info',
                'icon' => 'bi-check2-circle',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => $reception->getStatutLabel(),
                'actor' => $this->userName($reception->getReceivedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->stageDate($reception->getDateDebutTraitement(), $reception->getHeureDebutTraitement(), $reception->getTreatmentStartedAt()), $from, $to, [
                'stage' => 'traitement',
                'stage_label' => 'Traitement',
                'quantity_key' => 'prepared',
                'quantity' => $reception->getQuantiteTotalePrepareeValue(),
                'unit' => 'kg',
                'title' => 'Traitement lancé',
                'badge' => 'text-bg-info',
                'icon' => 'bi-arrow-repeat',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => sprintf('%s caisse(s), %.3f kg/caisse', $reception->getNombreCaissesApresTraitement(), (float) $reception->getPoidsMoyenParCaisse()),
                'actor' => $this->userName($reception->getTreatmentStartedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->combine($reception->getDateConditionnement(), $reception->getHeureDebutConditionnement()), $from, $to, [
                'stage' => 'emballage',
                'stage_label' => 'Emballage',
                'quantity_key' => 'packaged',
                'quantity' => $reception->getQuantiteConditionneeValue(),
                'unit' => 'kg',
                'title' => 'Conditionnement / emballage',
                'badge' => 'text-bg-warning',
                'icon' => 'bi-box',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => $reception->getProduitConditionne() ?: 'Produit conditionné non renseigne',
                'actor' => $this->userName($reception->getUpdatedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->combine($reception->getDateSortieTunnel(), $reception->getHeureEntreeTunnel()), $from, $to, [
                'stage' => 'congelation',
                'stage_label' => 'Congélation',
                'quantity_key' => 'frozen',
                'quantity' => $reception->getQuantiteCongeleeValue(),
                'unit' => 'kg',
                'title' => 'Congélation',
                'badge' => 'text-bg-primary',
                'icon' => 'bi-snow',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => trim(sprintf('%s - %.2f h tunnel', $reception->getTunnel() ?: 'Tunnel non renseigné', $reception->getDureeTunnelHeuresValue()), ' -'),
                'actor' => $this->userName($reception->getUpdatedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->stageDate($reception->getDateEntreeStockage(), $reception->getHeureEntreeStockage(), $reception->getStoredAt()), $from, $to, [
                'stage' => 'stockage',
                'stage_label' => 'Stockage',
                'quantity_key' => 'stored',
                'quantity' => $reception->getQuantiteStockeeValue(),
                'unit' => 'kg',
                'title' => 'Entree en stockage',
                'badge' => 'text-bg-success',
                'icon' => 'bi-box-seam',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => $reception->getChambreFroide() ?: 'Zone non renseignee',
                'actor' => $this->userName($reception->getStoredBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $this->stageDate($reception->getExpeditionDateDepart(), $reception->getExpeditionHeureDepart(), $reception->getExpeditedAt()), $from, $to, [
                'stage' => 'expedition',
                'stage_label' => 'Expédition',
                'quantity_key' => 'shipped',
                'quantity' => $reception->getQuantiteTotaleExpedieeValue(),
                'unit' => 'kg',
                'title' => 'Expédition',
                'badge' => 'text-bg-dark',
                'icon' => 'bi-truck',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => trim(sprintf('%s - %s - %s', (string) $reception->getDestinationFinaleClient(), (string) $reception->getExpeditionMatriculeVehicule(), (string) $reception->getExpeditionChauffeur()), ' -'),
                'actor' => $this->userName($reception->getExpeditedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $reception->getClosedAt(), $from, $to, [
                'stage' => 'cloture',
                'stage_label' => 'Clôture',
                'title' => 'Reception cloturee',
                'badge' => 'text-bg-dark',
                'icon' => 'bi-lock',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => $reception->getStatutLabel(),
                'actor' => $this->userName($reception->getClosedBy()),
                'reception' => $reception,
            ]);

            $this->addEvent($events, $reception->getBlockedAt(), $from, $to, [
                'stage' => 'blocage',
                'stage_label' => 'Blocage',
                'title' => 'Reception bloquee',
                'badge' => 'text-bg-danger',
                'icon' => 'bi-exclamation-octagon',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => $reception->getBlockReason() ?: 'Motif non renseigne',
                'actor' => $this->userName($reception->getBlockedBy()),
                'reception' => $reception,
            ]);

            foreach ($this->observationEvents($reception, $from, $to) as $event) {
                $events[] = $event;
            }
        }

        foreach ($costLots as $lot) {
            $this->addEvent($events, $this->combine($lot->getDateProduction(), null), $from, $to, [
                'stage' => 'cout',
                'stage_label' => 'Cout',
                'quantity_key' => 'cost_weight',
                'quantity' => (float) $lot->getPoidsMisEnProduction(),
                'unit' => 'kg',
                'title' => 'Lot coût de revient',
                'badge' => $lot->getStatutBadgeClass(),
                'icon' => 'bi-calculator',
                'reference' => (string) $lot->getNumeroLot(),
                'details' => sprintf('%s - %.2f dh total - %.2f dh/kg', (string) $lot->getProduit(), (float) $lot->getCoutTotalProduction(), (float) $lot->getCoutRevientKg()),
                'actor' => $this->userName($lot->getCreatedBy()),
                'cost_lot' => $lot,
            ]);
        }

        usort($events, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $events;
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<array<string, mixed>>
     */
    private function workflowSummary(array $events): array
    {
        $stages = [
            'reception' => ['label' => 'Reception', 'icon' => 'bi-clipboard2-check', 'quantity_key' => 'received', 'tone' => 'primary'],
            'traitement' => ['label' => 'Traitement', 'icon' => 'bi-arrow-repeat', 'quantity_key' => 'prepared', 'tone' => 'info'],
            'emballage' => ['label' => 'Emballage', 'icon' => 'bi-box', 'quantity_key' => 'packaged', 'tone' => 'warning'],
            'congelation' => ['label' => 'Congélation', 'icon' => 'bi-snow', 'quantity_key' => 'frozen', 'tone' => 'primary'],
            'stockage' => ['label' => 'Stockage', 'icon' => 'bi-box-seam', 'quantity_key' => 'stored', 'tone' => 'success'],
            'expedition' => ['label' => 'Expédition', 'icon' => 'bi-truck', 'quantity_key' => 'shipped', 'tone' => 'dark'],
        ];

        foreach ($stages as $key => $stage) {
            $stages[$key]['events'] = 0;
            $stages[$key]['quantity'] = 0.0;
        }

        foreach ($events as $event) {
            $stage = (string) ($event['stage'] ?? '');
            if (isset($stages[$stage])) {
                $stages[$stage]['events']++;
                $stages[$stage]['quantity'] += (float) ($event['quantity'] ?? 0);
            }
        }

        return array_values($stages);
    }

    /**
     * @param list<FishReception>         $receptions
     * @param list<CoutRevient>           $costLots
     * @param list<array<string, mixed>>  $events
     * @param list<array<string, mixed>>  $workflow
     *
     * @return array<string, mixed>
     */
    private function operationBasis(array $receptions, array $costLots, array $events, array $workflow, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $periodDays = max(1, ((int) $from->diff($to)->format('%a')) + 1);
        $workflowKg = max(0.0, ...array_map(static fn (array $stage): float => (float) $stage['quantity'], $workflow ?: [['quantity' => 0.0]]));
        $costLotKg = array_sum(array_map(static fn (CoutRevient $lot): float => (float) $lot->getPoidsMisEnProduction(), $costLots));
        $costLotHours = array_sum(array_map(static fn (CoutRevient $lot): float => (float) $lot->getNombreHeures(), $costLots));
        $workflowHours = $this->workflowKnownHours($receptions, $from, $to);
        $activeReceptionIds = [];

        foreach ($events as $event) {
            if (($event['reception'] ?? null) instanceof FishReception && in_array((string) ($event['stage'] ?? ''), ['reception', 'traitement', 'emballage', 'congelation', 'stockage', 'expedition'], true)) {
                $activeReceptionIds[(int) $event['reception']->getId()] = true;
            }
        }

        return [
            'period_days' => $periodDays,
            'kg_reference' => max($workflowKg, $costLotKg),
            'lot_reference' => max(count($costLots), count($activeReceptionIds)),
            'known_hours' => max($costLotHours, $workflowHours),
            'workflow_hours' => $workflowHours,
            'cost_lot_hours' => $costLotHours,
            'has_activity' => count($events) > 0,
        ];
    }

    /**
     * @param list<CoutRevientChargeConfig> $configs
     * @param array<string, mixed>          $operation
     *
     * @return array<string, mixed>
     */
    private function estimateCharges(array $configs, array $operation): array
    {
        $lines = [];
        $byCategory = [];
        $total = 0.0;

        foreach ($configs as $config) {
            $unitCost = (float) $config->getUnitCost();
            [$quantity, $appliedUnitCost, $basis] = match ($config->getCalculationUnit()) {
                CoutRevientChargeConfig::UNIT_MONTH => [(float) $operation['period_days'], $unitCost / 30.0, 'Prorata mois sur '.$operation['period_days'].' jour(s)'],
                CoutRevientChargeConfig::UNIT_DAY => [(float) $operation['period_days'], $unitCost, 'Nombre de jours dans la plage'],
                CoutRevientChargeConfig::UNIT_HOUR => [(float) $operation['known_hours'], $unitCost, 'Heures connues workflow / lots cout'],
                CoutRevientChargeConfig::UNIT_KG => [(float) $operation['kg_reference'], $unitCost, 'Kg référence de la période'],
                CoutRevientChargeConfig::UNIT_LOT => [(float) $operation['lot_reference'], $unitCost, 'Nombre de lots/réceptions actifs'],
                default => [(bool) $operation['has_activity'] ? 1.0 : 0.0, $unitCost, 'Forfait applique une fois si activite'],
            };

            $lineTotal = $quantity * $appliedUnitCost;
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

            $lines[] = [
                'name' => (string) $config->getName(),
                'category' => $category,
                'category_label' => $config->getCategoryLabel(),
                'calculation_unit' => $config->getCalculationUnit(),
                'unit_label' => $config->getCalculationUnitLabel(),
                'unit_short' => $config->getCalculationUnitShortLabel(),
                'unit_cost' => $unitCost,
                'applied_unit_cost' => $appliedUnitCost,
                'quantity' => $quantity,
                'total' => $lineTotal,
                'basis' => $basis,
                'factory_unit' => $config->getFactoryUnit()?->getDisplayName(),
                'needs_attention' => $lineTotal <= 0.0 && in_array($config->getCalculationUnit(), [CoutRevientChargeConfig::UNIT_HOUR, CoutRevientChargeConfig::UNIT_KG], true),
            ];
        }

        uasort($byCategory, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'lines' => $lines,
            'by_category' => array_values($byCategory),
            'total' => $total,
            'count' => count($configs),
        ];
    }

    /**
     * @param list<CoutRevient> $costLots
     *
     * @return array<string, float>
     */
    private function costLotStats(array $costLots): array
    {
        $totalCost = array_sum(array_map(static fn (CoutRevient $lot): float => (float) $lot->getCoutTotalProduction(), $costLots));
        $finishedWeight = array_sum(array_map(static fn (CoutRevient $lot): float => (float) $lot->getPoidsProduitFini(), $costLots));
        $charges = array_sum(array_map(static fn (CoutRevient $lot): float => (float) $lot->getCoutChargesTotal(), $costLots));

        return [
            'total_cost' => $totalCost,
            'finished_weight' => $finishedWeight,
            'charges' => $charges,
        ];
    }

    /**
     * @param array<string, mixed> $charges
     * @param array<string, mixed> $operation
     * @param list<CoutRevient>    $costLots
     *
     * @return list<array{tone: string, text: string}>
     */
    private function warnings(array $charges, array $operation, array $costLots): array
    {
        $warnings = [];
        if ((int) $charges['count'] === 0) {
            $warnings[] = ['tone' => 'warning', 'text' => 'Aucune charge active declaree dans Charges production. Activez au moins les charges fixes pour obtenir une estimation.'];
        }

        if ((float) $operation['known_hours'] <= 0.0 && $this->hasUnit($charges['lines'], CoutRevientChargeConfig::UNIT_HOUR)) {
            $warnings[] = ['tone' => 'warning', 'text' => 'Des charges par heure existent, mais aucune heure exploitable n est trouvee sur la periode.'];
        }

        if ((float) $operation['kg_reference'] <= 0.0 && $this->hasUnit($charges['lines'], CoutRevientChargeConfig::UNIT_KG)) {
            $warnings[] = ['tone' => 'warning', 'text' => 'Des charges au kg existent, mais aucun kg de référence n\'est trouvé sur la période.'];
        }

        if ($costLots === []) {
            $warnings[] = ['tone' => 'info', 'text' => 'Aucun lot coût de revient n\'est validé sur cette plage. La page affiche donc surtout une estimation à partir des charges configurées.'];
        }

        return $warnings;
    }

    /** @param list<array<string, mixed>> $lines */
    private function hasUnit(array $lines, string $unit): bool
    {
        foreach ($lines as $line) {
            if (($line['calculation_unit'] ?? null) === $unit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $events
     */
    private function addEvent(array &$events, ?\DateTimeImmutable $at, \DateTimeImmutable $from, \DateTimeImmutable $to, array $payload): void
    {
        if (!$at instanceof \DateTimeImmutable || $at < $from || $at > $to) {
            return;
        }

        if (($payload['quantity'] ?? 0) === 0.0 && in_array((string) ($payload['stage'] ?? ''), ['traitement', 'emballage', 'congelation', 'stockage', 'expedition'], true)) {
            return;
        }

        $payload['at'] = $at;
        $payload['date_key'] = $at->format('Y-m-d');
        $events[] = $payload;
    }

    /** @return list<array<string, mixed>> */
    private function observationEvents(FishReception $reception, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $observations = (string) $reception->getObservations();
        if ($observations === '' || !preg_match_all('/\[(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})\]\s*([^\r\n]+)/', $observations, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $events = [];
        foreach ($matches as $match) {
            $at = new \DateTimeImmutable(sprintf('%s-%s-%s %s:%s:00', $match[3], $match[2], $match[1], $match[4], $match[5]));
            if ($at < $from || $at > $to) {
                continue;
            }

            $events[] = [
                'at' => $at,
                'date_key' => $at->format('Y-m-d'),
                'stage' => 'annulation',
                'stage_label' => 'Annulation',
                'title' => 'Trace observation',
                'badge' => 'text-bg-danger',
                'icon' => 'bi-arrow-counterclockwise',
                'reference' => (string) $reception->getNumeroReception(),
                'details' => trim($match[6]),
                'actor' => null,
                'reception' => $reception,
            ];
        }

        return $events;
    }

    private function workflowKnownHours(array $receptions, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        $hours = 0.0;
        foreach ($receptions as $reception) {
            if ($this->inDateRange($reception->getDateReception(), $from, $to)) {
                $hours += $this->durationHours($reception->getDateReception(), $reception->getHeureDebutReception(), $reception->getHeureFinReception());
            }
            if ($this->inDateRange($reception->getDateConditionnement(), $from, $to)) {
                $hours += $this->durationHours($reception->getDateConditionnement(), $reception->getHeureDebutConditionnement(), $reception->getHeureFinConditionnement());
            }
            if ($this->inDateRange($reception->getDateSortieTunnel(), $from, $to)) {
                $hours += $reception->getDureeTunnelHeuresValue();
            }
        }

        return $hours;
    }

    private function durationHours(?\DateTimeImmutable $date, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end): float
    {
        if (!$date instanceof \DateTimeImmutable || !$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return 0.0;
        }

        $startAt = $this->combine($date, $start);
        $endAt = $this->combine($date, $end);
        if (!$startAt instanceof \DateTimeImmutable || !$endAt instanceof \DateTimeImmutable) {
            return 0.0;
        }

        if ($endAt <= $startAt) {
            $endAt = $endAt->modify('+1 day');
        }

        return max(0.0, ($endAt->getTimestamp() - $startAt->getTimestamp()) / 3600);
    }

    private function stageDate(?\DateTimeImmutable $date, ?\DateTimeImmutable $time, ?\DateTimeImmutable $fallback): ?\DateTimeImmutable
    {
        return $this->combine($date, $time) ?? $fallback;
    }

    private function combine(?\DateTimeImmutable $date, ?\DateTimeImmutable $time): ?\DateTimeImmutable
    {
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return new \DateTimeImmutable($date->format('Y-m-d').' '.($time instanceof \DateTimeImmutable ? $time->format('H:i:s') : '00:00:00'));
    }

    private function inDateRange(?\DateTimeImmutable $date, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        if (!$date instanceof \DateTimeImmutable) {
            return false;
        }

        $day = $date->setTime(12, 0);

        return $day >= $from && $day <= $to;
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

    private function userName(?User $user): ?string
    {
        return $user?->getDisplayName();
    }
}
