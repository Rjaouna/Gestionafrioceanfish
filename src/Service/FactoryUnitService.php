<?php

namespace App\Service;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\FactoryUnit;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\FishReceptionRepository;
use App\Repository\FactoryUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class FactoryUnitService
{
    public function __construct(
        private FactoryUnitRepository $repository,
        private CoutRevientChargeConfigRepository $chargeRepository,
        private FishReceptionRepository $fishReceptionRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
    ) {
    }

    /** @return list<FactoryUnit> */
    public function search(User $actor, array $filters = []): array
    {
        $this->assertAccess($actor);

        return $this->repository->search(
            (string) ($filters['q'] ?? ''),
            (string) ($filters['type'] ?? ''),
            (string) ($filters['status'] ?? ''),
            (string) ($filters['saturation'] ?? ''),
        );
    }

    public function save(FactoryUnit $unit, User $actor): FactoryUnit
    {
        $this->assertAccess($actor);
        $this->prepare($unit);

        if ($unit->getId() === null) {
            $unit->setCreatedBy($actor);
            $this->entityManager->persist($unit);
        } else {
            $unit->setUpdatedBy($actor);
        }

        $this->syncChargeConfig($unit, $actor);
        $this->entityManager->flush();

        return $unit;
    }

    public function toggleSaturation(FactoryUnit $unit, User $actor): bool
    {
        $this->assertAccess($actor);
        $unit
            ->setIsSaturated(!$unit->isSaturated())
            ->setUpdatedBy($actor);

        $this->entityManager->flush();

        return $unit->isSaturated();
    }

    public function toggleActive(FactoryUnit $unit, User $actor): bool
    {
        $this->assertAccess($actor);
        $unit
            ->setIsActive(!$unit->isActive())
            ->setUpdatedBy($actor);

        $charge = $unit->getId() !== null ? $this->chargeRepository->findOneBy(['factoryUnit' => $unit]) : null;
        if ($charge instanceof CoutRevientChargeConfig && !$unit->isActive()) {
            $charge
                ->setIsActive(false)
                ->setUpdatedBy($actor);
        }

        $this->entityManager->flush();

        return $unit->isActive();
    }

    /** @return array<string, string> */
    public function tunnelChoices(User $actor, ?string $current = null): array
    {
        $this->assertUsageAccess($actor);

        return $this->choicesFromUnits(
            $this->repository->usableByTypes([FactoryUnit::TYPE_TUNNEL]),
            $current,
        );
    }

    /** @return array<string, string> */
    public function storageChoices(User $actor, ?string $current = null): array
    {
        $this->assertUsageAccess($actor);

        return $this->choicesFromUnits(
            $this->repository->usableByTypes(array_keys(FactoryUnit::TYPE_LABELS)),
            $current,
        );
    }

    /** @return array<string, mixed> */
    public function storageOverview(User $actor): array
    {
        $this->assertUsageAccess($actor);

        $units = $this->repository->search();
        $stockByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentStockByStorageLocation());
        $tunnelByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentLoadByTunnel());

        $stateCounts = [
            'Disponible' => 0,
            'Occupee' => 0,
            'Saturee' => 0,
            'Maintenance' => 0,
            'Arretee' => 0,
            'Masquee' => 0,
        ];
        $typeCounts = [];
        $spaces = [];

        foreach ($units as $unit) {
            $typeLabel = $unit->getTypeLabel();
            $typeCounts[$typeLabel] = ($typeCounts[$typeLabel] ?? 0) + 1;

            $stock = $this->stockForUnit($unit, $stockByLocationKey);
            if ($unit->getType() === FactoryUnit::TYPE_TUNNEL) {
                $stock += $this->stockForUnit($unit, $tunnelByLocationKey);
            }
            $capacity = (float) $unit->getCapacityKg();
            $percent = $capacity > 0.001 ? ($stock / $capacity) * 100 : 0.0;
            $isFullByCapacity = $capacity > 0.001 && $stock >= $capacity - 0.001;

            if (!$unit->isActive()) {
                ++$stateCounts['Masquee'];
            } elseif ($unit->getStatus() === FactoryUnit::STATUS_STOPPED) {
                ++$stateCounts['Arretee'];
            } elseif ($unit->getStatus() === FactoryUnit::STATUS_MAINTENANCE) {
                ++$stateCounts['Maintenance'];
            } elseif ($unit->isSaturated() || $isFullByCapacity) {
                ++$stateCounts['Saturee'];
            } elseif ($stock > 0.001) {
                ++$stateCounts['Occupee'];
            } else {
                ++$stateCounts['Disponible'];
            }

            $spaces[] = [
                'name' => $unit->getDisplayName(),
                'code' => $unit->getCode(),
                'type_label' => $typeLabel,
                'status_label' => $unit->getStatusLabel(),
                'capacity' => $capacity,
                'capacity_display' => $capacity > 0.001 ? $this->formatKg($capacity) : 'Capacite non renseignee',
                'load' => $stock,
                'load_display' => $this->formatKg($stock),
                'free' => $capacity > 0.001 ? max(0.0, $capacity - $stock) : 0.0,
                'free_display' => $capacity > 0.001 ? $this->formatKg(max(0.0, $capacity - $stock)) : '-',
                'percent' => $percent,
                'percent_display' => $capacity > 0.001 ? number_format(min(999.0, $percent), 0, ',', ' ').' %' : ($stock > 0.001 ? 'Cap. ?' : 'Vide'),
                'progress' => $capacity > 0.001 ? min(100, max(0, (int) round($percent))) : ($stock > 0.001 ? 100 : 0),
                'tone' => $this->spaceTone($unit, $stock, $percent, $capacity),
                'state_label' => $this->spaceStateLabel($unit, $stock, $percent, $capacity),
            ];
        }

        $totalStock = array_sum(array_map(static fn (array $space): float => (float) $space['load'], $spaces));

        return [
            'total_units' => count($units),
            'total_stock' => $totalStock,
            'status_chart' => $this->chartFromCounts($stateCounts),
            'type_chart' => $this->chartFromCounts($typeCounts),
            'spaces' => $spaces,
            'total_stock_display' => $this->formatKg($totalStock),
        ];
    }

    /** @param list<FactoryUnit> $units @return array<string, string> */
    private function choicesFromUnits(array $units, ?string $current = null): array
    {
        $choices = [];
        foreach ($units as $unit) {
            $label = sprintf('%s (%s)', $unit->getDisplayName(), $unit->getTypeLabel());
            $choices[$label] = (string) $unit->getCode();
        }

        $current = trim((string) $current);
        if ($current !== '' && !in_array($current, $choices, true)) {
            $choices['Valeur actuelle - '.$current] = $current;
        }

        return $choices;
    }

    /**
     * @param list<array{location: string, quantity: string}> $rows
     *
     * @return array<string, float>
     */
    private function loadRowsByLocation(array $rows): array
    {
        $loads = [];
        foreach ($rows as $row) {
            $location = trim((string) ($row['location'] ?? ''));
            $quantity = max(0.0, (float) ($row['quantity'] ?? 0));
            if ($location === '' || $quantity <= 0.001) {
                continue;
            }

            $key = $this->normalizeLocationKey($location);
            $loads[$key] = ($loads[$key] ?? 0.0) + $quantity;
        }

        return $loads;
    }

    /** @param array<string, float> $stockByLocationKey */
    private function stockForUnit(FactoryUnit $unit, array $stockByLocationKey): float
    {
        $stock = 0.0;
        foreach ($this->unitLocationKeys($unit) as $key) {
            $stock += $stockByLocationKey[$key] ?? 0.0;
        }

        return $stock;
    }

    /** @return list<string> */
    private function unitLocationKeys(FactoryUnit $unit): array
    {
        $keys = [
            $this->normalizeLocationKey((string) $unit->getCode()),
            $this->normalizeLocationKey((string) $unit->getName()),
            $this->normalizeLocationKey($unit->getDisplayName()),
        ];

        return array_values(array_unique(array_filter($keys)));
    }

    private function normalizeLocationKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function spaceTone(FactoryUnit $unit, float $load, float $percent, float $capacity): string
    {
        if (!$unit->isActive() || $unit->getStatus() !== FactoryUnit::STATUS_OPERATIONAL) {
            return 'secondary';
        }

        if ($unit->isSaturated() || $percent > 60) {
            return 'danger';
        }

        if ($percent > 40 || ($capacity <= 0.001 && $load > 0.001)) {
            return 'warning';
        }

        return 'success';
    }

    private function spaceStateLabel(FactoryUnit $unit, float $load, float $percent, float $capacity): string
    {
        if (!$unit->isActive()) {
            return 'Masquee';
        }

        if ($unit->getStatus() === FactoryUnit::STATUS_STOPPED) {
            return 'Arretee';
        }

        if ($unit->getStatus() === FactoryUnit::STATUS_MAINTENANCE) {
            return 'Maintenance';
        }

        if ($unit->isSaturated()) {
            return 'Saturee';
        }

        if ($capacity <= 0.001 && $load > 0.001) {
            return 'Capacite a renseigner';
        }

        if ($percent > 60) {
            return 'Remplie';
        }

        if ($percent > 40) {
            return 'A surveiller';
        }

        return $load > 0.001 ? 'Disponible' : 'Vide';
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array<string, mixed>>
     */
    private function chartFromCounts(array $counts): array
    {
        $items = [];
        foreach ($counts as $label => $value) {
            if ($value <= 0) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'short_label' => mb_substr($label, 0, 12),
                'value' => $value,
                'display' => (string) $value,
            ];
        }

        return $this->withShares($items);
    }

    /**
     * @param array<string, float> $weights
     *
     * @return list<array<string, mixed>>
     */
    private function chartFromWeights(array $weights): array
    {
        $items = [];
        foreach ($weights as $label => $value) {
            if ($value <= 0.001) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'short_label' => mb_substr($label, 0, 12),
                'value' => round($value, 3),
                'display' => $this->formatKg($value),
            ];
        }

        return $this->withShares($items);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function withShares(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $values = array_map(static fn (array $item): float => (float) $item['value'], $items);
        $max = max(1.0, ...$values);
        $total = max(1.0, array_sum($values));
        $offset = 0.0;
        $chart = [];

        foreach ($items as $item) {
            $value = (float) $item['value'];
            $share = $value > 0 ? round(($value / $total) * 100, 2) : 0.0;
            $chart[] = $item + [
                'percent' => $value > 0 ? max(8, (int) round(($value / $max) * 100)) : 0,
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $chart;
    }

    private function formatKg(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');

        return ($formatted === '' ? '0' : $formatted).' kg';
    }

    private function prepare(FactoryUnit $unit): void
    {
        if (trim((string) $unit->getCode()) === '') {
            $unit->setCode($this->nextCode($unit->getType()));
        }

        $existing = $this->repository->findOneBy(['code' => $unit->getCode()]);
        if ($existing instanceof FactoryUnit && $existing->getId() !== $unit->getId()) {
            throw new \DomainException('Cette reference usine existe deja.');
        }

        if ($unit->getStatus() !== FactoryUnit::STATUS_OPERATIONAL) {
            $unit->setIsSaturated(false);
        }
    }

    private function syncChargeConfig(FactoryUnit $unit, User $actor): void
    {
        $charge = $this->chargeRepository->findOneBy(['factoryUnit' => $unit]);
        $isNew = !$charge instanceof CoutRevientChargeConfig;
        if ($isNew) {
            $charge = new CoutRevientChargeConfig();
            $charge
                ->setFactoryUnit($unit)
                ->setIsActive(false)
                ->setUnitCost(0)
                ->setSortOrder($unit->getSortOrder())
                ->setCreatedBy($actor);
            $this->entityManager->persist($charge);
        } else {
            $charge->setUpdatedBy($actor);
        }

        $charge
            ->setName($unit->getDisplayName())
            ->setCategory($this->chargeCategoryFor($unit))
            ->setCalculationUnit($this->chargeUnitFor($unit));

        if ($isNew) {
            $charge->setDescription('Charge creee automatiquement depuis la composition usine. Renseignez le cout puis activez-la si elle doit etre proposee par defaut.');
        }

        if (!$unit->isActive() || $unit->getStatus() === FactoryUnit::STATUS_STOPPED) {
            $charge->setIsActive(false);
        }
    }

    private function chargeCategoryFor(FactoryUnit $unit): string
    {
        return match ($unit->getType()) {
            FactoryUnit::TYPE_TUNNEL => CoutRevientChargeConfig::CATEGORY_COLD,
            FactoryUnit::TYPE_NEGATIVE_ROOM, FactoryUnit::TYPE_POSITIVE_ROOM, FactoryUnit::TYPE_STORAGE_ZONE => CoutRevientChargeConfig::CATEGORY_STORAGE,
            FactoryUnit::TYPE_PRODUCTION_ZONE, FactoryUnit::TYPE_PACKAGING_ZONE => CoutRevientChargeConfig::CATEGORY_PRODUCTION,
            default => CoutRevientChargeConfig::CATEGORY_OTHER,
        };
    }

    private function chargeUnitFor(FactoryUnit $unit): string
    {
        return match ($unit->getType()) {
            FactoryUnit::TYPE_TUNNEL => CoutRevientChargeConfig::UNIT_HOUR,
            FactoryUnit::TYPE_NEGATIVE_ROOM, FactoryUnit::TYPE_POSITIVE_ROOM, FactoryUnit::TYPE_STORAGE_ZONE => CoutRevientChargeConfig::UNIT_DAY,
            FactoryUnit::TYPE_PRODUCTION_ZONE, FactoryUnit::TYPE_PACKAGING_ZONE => CoutRevientChargeConfig::UNIT_LOT,
            default => CoutRevientChargeConfig::UNIT_DIRECT,
        };
    }

    private function nextCode(string $type): string
    {
        $prefix = match ($type) {
            FactoryUnit::TYPE_TUNNEL => 'TUN',
            FactoryUnit::TYPE_NEGATIVE_ROOM => 'CHN',
            FactoryUnit::TYPE_POSITIVE_ROOM => 'CHP',
            FactoryUnit::TYPE_PRODUCTION_ZONE => 'PRD',
            FactoryUnit::TYPE_PACKAGING_ZONE => 'EMB',
            FactoryUnit::TYPE_STORAGE_ZONE => 'STK',
            default => 'USN',
        };
        $sequence = $this->repository->countByCodePrefix($prefix.'-') + 1;

        do {
            $code = sprintf('%s-%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['code' => $code]) instanceof FactoryUnit);

        return $code;
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccessModule($actor, 'factory')) {
            throw new AccessDeniedException();
        }
    }

    private function assertUsageAccess(User $actor): void
    {
        if (
            !$this->access->canAccessModule($actor, 'factory')
            && !$this->access->canAccessModule($actor, 'receptions')
            && !$this->access->canAccessModule($actor, 'cout-revient')
        ) {
            throw new AccessDeniedException();
        }
    }
}
