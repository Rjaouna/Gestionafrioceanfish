<?php

namespace App\Service;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\FactoryUnit;
use App\Entity\User;
use App\Repository\CoutRevientChargeLineRepository;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\FishReceptionRepository;
use App\Repository\FishReceptionStorageMovementRepository;
use App\Repository\FactoryUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class FactoryUnitService
{
    private const TEMPERATURE_DEFAULTS = [
        FactoryUnit::TYPE_TUNNEL => ['target' => -40.0, 'min' => -45.0, 'max' => -30.0],
        FactoryUnit::TYPE_NEGATIVE_ROOM => ['target' => -20.0, 'min' => -25.0, 'max' => -18.0],
        FactoryUnit::TYPE_POSITIVE_ROOM => ['target' => 2.0, 'min' => 0.0, 'max' => 4.0],
        FactoryUnit::TYPE_PRODUCTION_ZONE => ['target' => 12.0, 'min' => 8.0, 'max' => 15.0],
        FactoryUnit::TYPE_PACKAGING_ZONE => ['target' => 10.0, 'min' => 6.0, 'max' => 12.0],
        FactoryUnit::TYPE_STORAGE_ZONE => ['target' => -18.0, 'min' => -22.0, 'max' => -15.0],
        FactoryUnit::TYPE_OTHER => ['target' => 18.0, 'min' => 10.0, 'max' => 25.0],
    ];

    private const CAPACITY_KG_PER_M2 = [
        FactoryUnit::TYPE_TUNNEL => 350.0,
        FactoryUnit::TYPE_NEGATIVE_ROOM => 600.0,
        FactoryUnit::TYPE_POSITIVE_ROOM => 550.0,
        FactoryUnit::TYPE_STORAGE_ZONE => 500.0,
        FactoryUnit::TYPE_PRODUCTION_ZONE => 250.0,
        FactoryUnit::TYPE_PACKAGING_ZONE => 250.0,
        FactoryUnit::TYPE_OTHER => 300.0,
    ];

    private const DIMENSION_RATIO = [
        FactoryUnit::TYPE_TUNNEL => 2.4,
        FactoryUnit::TYPE_PRODUCTION_ZONE => 1.8,
        FactoryUnit::TYPE_PACKAGING_ZONE => 1.8,
        FactoryUnit::TYPE_OTHER => 1.5,
    ];

    private const STORAGE_LOCATION_TYPES = [
        FactoryUnit::TYPE_NEGATIVE_ROOM,
        FactoryUnit::TYPE_POSITIVE_ROOM,
        FactoryUnit::TYPE_PRODUCTION_ZONE,
        FactoryUnit::TYPE_PACKAGING_ZONE,
        FactoryUnit::TYPE_STORAGE_ZONE,
        FactoryUnit::TYPE_OTHER,
    ];

    public function __construct(
        private FactoryUnitRepository $repository,
        private CoutRevientChargeConfigRepository $chargeRepository,
        private CoutRevientChargeLineRepository $chargeLineRepository,
        private FishReceptionRepository $fishReceptionRepository,
        private FishReceptionStorageMovementRepository $storageMovementRepository,
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

    public function delete(FactoryUnit $unit, User $actor): void
    {
        $this->assertAccess($actor);
        $load = $this->currentLoadForUnit($unit);
        if ($load > 0.001) {
            throw new \DomainException(sprintf(
                'Impossible de supprimer %s : la piece contient encore %s.',
                $unit->getDisplayName(),
                $this->formatKg($load),
            ));
        }

        $charge = $this->chargeRepository->findOneBy(['factoryUnit' => $unit]);
        if ($charge instanceof CoutRevientChargeConfig) {
            if ($this->chargeLineRepository->countForConfig($charge) > 0) {
                $charge
                    ->setFactoryUnit(null)
                    ->setIsActive(false)
                    ->setUpdatedBy($actor);
            } else {
                $this->entityManager->remove($charge);
            }
        }

        $this->entityManager->remove($unit);
        $this->entityManager->flush();
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

        $current = $this->findUnitByLocationValue((string) $current, [FactoryUnit::TYPE_TUNNEL]) instanceof FactoryUnit ? null : $current;

        return $this->choicesFromUnits(
            $this->repository->usableByTypes(self::STORAGE_LOCATION_TYPES),
            $current,
        );
    }

    /** @return array<string, string> */
    public function positiveStorageChoices(User $actor, ?string $current = null): array
    {
        $this->assertUsageAccess($actor);
        $units = $this->usableChamberStorageUnits();
        if ($units === []) {
            $units = $this->usableNonTunnelStorageUnits();
        }

        return $this->choicesFromUnits(
            $units,
            $current,
        );
    }

    /** @return array<string, mixed> */
    public function storageOverview(User $actor): array
    {
        $this->assertUsageAccess($actor);

        $units = $this->repository->search();
        $stockByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentStockByStorageLocation());
        $crystallizationStockByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentCrystallizationStockByStorageLocation());
        $initialStockByLocationKey = $this->loadRowsByLocation($this->storageMovementRepository->currentInitialStockByStorageLocation());
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
            $stock += $this->stockForUnit($unit, $crystallizationStockByLocationKey);
            $stock += $this->stockForUnit($unit, $initialStockByLocationKey);
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
                'percent_display' => $capacity > 0.001 ? $this->formatPercent($percent) : ($stock > 0.001 ? 'Cap. ?' : 'Vide'),
                'progress' => $capacity > 0.001 ? $this->spaceProgress($percent, $stock) : ($stock > 0.001 ? 100 : 0),
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

    /** @return array<string, mixed> */
    public function tunnelCapacityDiagnostic(User $actor, ?string $tunnel, float $requestedQuantity): array
    {
        return $this->factoryUnitCapacityDiagnostic(
            $actor,
            $tunnel,
            $requestedQuantity,
            [FactoryUnit::TYPE_TUNNEL],
            'Tunnel',
            'Sélectionnez un tunnel avant de valider la congélation.',
            'Ce tunnel n\'existe pas dans Composition usine. Sélectionnez un tunnel déclaré pour contrôler la capacité.',
        );
    }

    public function assertTunnelCanReceive(User $actor, ?string $tunnel, float $requestedQuantity): void
    {
        $diagnostic = $this->tunnelCapacityDiagnostic($actor, $tunnel, $requestedQuantity);
        if (($diagnostic['canSubmit'] ?? false) !== true) {
            throw new \DomainException((string) ($diagnostic['message'] ?? 'Capacité tunnel insuffisante.'));
        }
    }

    /** @return array<string, mixed> */
    public function storageCapacityDiagnostic(User $actor, ?string $location, float $requestedQuantity): array
    {
        return $this->factoryUnitCapacityDiagnostic(
            $actor,
            $location,
            $requestedQuantity,
            self::STORAGE_LOCATION_TYPES,
            'Espace de stockage',
            'Sélectionnez la chambre froide ou la zone de stockage avant de valider.',
            'Cet espace n\'existe pas dans Composition usine ou correspond à un tunnel. Sélectionnez une chambre froide ou une zone de stockage déclarée.',
        );
    }

    public function assertStorageCanReceive(User $actor, ?string $location, float $requestedQuantity): void
    {
        $diagnostic = $this->storageCapacityDiagnostic($actor, $location, $requestedQuantity);
        if (($diagnostic['canSubmit'] ?? false) !== true) {
            throw new \DomainException((string) ($diagnostic['message'] ?? 'Capacité espace stockage insuffisante.'));
        }
    }

    /** @return array<string, mixed> */
    public function positiveStorageCapacityDiagnostic(User $actor, ?string $location, float $requestedQuantity): array
    {
        return $this->factoryUnitCapacityDiagnostic(
            $actor,
            $location,
            $requestedQuantity,
            [FactoryUnit::TYPE_POSITIVE_ROOM, FactoryUnit::TYPE_NEGATIVE_ROOM],
            'Chambre',
            'Selectionnez la chambre avant de valider.',
            'Cette chambre n existe pas dans Composition usine ou n est pas declaree comme chambre.',
            $this->findPositiveStorageUnitByLocationValue((string) $location),
        );
    }

    public function assertPositiveStorageCanReceive(User $actor, ?string $location, float $requestedQuantity): void
    {
        $diagnostic = $this->positiveStorageCapacityDiagnostic($actor, $location, $requestedQuantity);
        if (($diagnostic['canSubmit'] ?? false) !== true) {
            throw new \DomainException((string) ($diagnostic['message'] ?? 'Capacite chambre insuffisante.'));
        }
    }

    /**
     * @param list<string> $types
     *
     * @return array<string, mixed>
     */
    private function factoryUnitCapacityDiagnostic(User $actor, ?string $location, float $requestedQuantity, array $types, string $spaceLabel, string $missingMessage, string $notFoundMessage, ?FactoryUnit $resolvedUnit = null): array
    {
        $this->assertUsageAccess($actor);

        $location = trim((string) $location);
        $requestedQuantity = max(0.0, $requestedQuantity);
        if ($location === '') {
            return $this->capacityDiagnosticPayload(
                null,
                $requestedQuantity,
                0.0,
                false,
                'danger',
                $spaceLabel.' obligatoire',
                $missingMessage,
            );
        }

        $unit = $resolvedUnit ?? $this->findUnitByLocationValue($location, $types);
        if (!$unit instanceof FactoryUnit) {
            return $this->capacityDiagnosticPayload(
                null,
                $requestedQuantity,
                0.0,
                false,
                'danger',
                $spaceLabel.' introuvable',
                $notFoundMessage,
            );
        }

        $currentLoad = $this->currentLoadForUnit($unit);
        $capacity = (float) $unit->getCapacityKg();
        $projectedLoad = $currentLoad + $requestedQuantity;
        $percentAfter = $capacity > 0.001 ? ($projectedLoad / $capacity) * 100 : 0.0;
        $canSubmit = true;
        $tone = 'success';
        $title = 'Capacite OK';
        $message = sprintf(
            '%s peut recevoir %s. Charge actuelle %s, apres validation %s sur %s.',
            $unit->getDisplayName(),
            $this->formatKg($requestedQuantity),
            $this->formatKg($currentLoad),
            $this->formatKg($projectedLoad),
            $capacity > 0.001 ? $this->formatKg($capacity) : 'capacité non renseignée',
        );

        if (!$unit->isActive()) {
            $canSubmit = false;
            $tone = 'danger';
            $title = $spaceLabel.' masque';
            $message = sprintf('%s est masque dans Composition usine.', $unit->getDisplayName());
        } elseif ($unit->getStatus() !== FactoryUnit::STATUS_OPERATIONAL) {
            $canSubmit = false;
            $tone = 'danger';
            $title = $spaceLabel.' non operationnel';
            $message = sprintf('%s est %s.', $unit->getDisplayName(), mb_strtolower($unit->getStatusLabel()));
        } elseif ($unit->isSaturated()) {
            $canSubmit = false;
            $tone = 'danger';
            $title = $spaceLabel.' sature';
            $message = sprintf('%s est marque sature dans Composition usine.', $unit->getDisplayName());
        } elseif ($capacity <= 0.001) {
            $canSubmit = false;
            $tone = 'danger';
            $title = 'Capacite manquante';
            $message = sprintf('Renseignez la capacité kg de %s dans Composition usine avant de valider.', $unit->getDisplayName());
        } elseif ($requestedQuantity <= 0.001) {
            $canSubmit = false;
            $tone = 'danger';
            $title = 'Quantite obligatoire';
            $message = 'Renseignez une quantité supérieure à 0 kg.';
        } elseif ($projectedLoad - $capacity > 0.001) {
            $canSubmit = false;
            $tone = 'danger';
            $title = 'Capacite depassee';
            $message = sprintf(
                '%s ne peut pas recevoir %s : charge actuelle %s, capacité %s, disponible %s.',
                $unit->getDisplayName(),
                $this->formatKg($requestedQuantity),
                $this->formatKg($currentLoad),
                $this->formatKg($capacity),
                $this->formatKg(max(0.0, $capacity - $currentLoad)),
            );
        } elseif ($percentAfter > 60) {
            $tone = 'danger';
            $title = $spaceLabel.' tres charge';
            $message .= ' L espace depassera 60 %, a surveiller avant de continuer.';
        } elseif ($percentAfter > 40) {
            $tone = 'warning';
            $title = $spaceLabel.' a surveiller';
            $message .= ' L espace depassera 40 %.';
        }

        return $this->capacityDiagnosticPayload($unit, $requestedQuantity, $currentLoad, $canSubmit, $tone, $title, $message);
    }

    /** @return list<FactoryUnit> */
    private function usableChamberStorageUnits(): array
    {
        $units = [];
        foreach ($this->repository->search() as $unit) {
            if (
                $this->isChamberStorageUnit($unit)
                && $unit->isActive()
                && $unit->getStatus() === FactoryUnit::STATUS_OPERATIONAL
                && !$unit->isSaturated()
            ) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    /** @return list<FactoryUnit> */
    private function usableNonTunnelStorageUnits(): array
    {
        $units = [];
        foreach ($this->repository->usableByTypes(self::STORAGE_LOCATION_TYPES) as $unit) {
            if ($unit->getType() !== FactoryUnit::TYPE_TUNNEL) {
                $units[] = $unit;
            }
        }

        return $units;
    }

    private function isChamberStorageUnit(FactoryUnit $unit): bool
    {
        if (in_array($unit->getType(), [FactoryUnit::TYPE_POSITIVE_ROOM, FactoryUnit::TYPE_NEGATIVE_ROOM], true)) {
            return true;
        }

        if ($unit->getType() === FactoryUnit::TYPE_TUNNEL) {
            return false;
        }

        $haystack = mb_strtolower(implode(' ', [
            $unit->getName(),
            $unit->getCode(),
            $unit->getDisplayName(),
            $unit->getLocationLabel(),
            $unit->getDescription(),
        ]));

        return str_contains($haystack, 'chambre')
            || str_contains($haystack, 'chombre')
            || str_contains($haystack, 'positive')
            || str_contains($haystack, 'negative')
            || str_contains($haystack, 'négative');
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

    /** @param list<string> $types */
    private function findUnitByLocationValue(string $value, array $types): ?FactoryUnit
    {
        $key = $this->normalizeLocationKey($value);
        if ($key === '') {
            return null;
        }

        foreach ($this->repository->search() as $unit) {
            if ($types !== [] && !in_array($unit->getType(), $types, true)) {
                continue;
            }

            if (in_array($key, $this->unitLocationKeys($unit), true)) {
                return $unit;
            }
        }

        return null;
    }

    private function findPositiveStorageUnitByLocationValue(string $value): ?FactoryUnit
    {
        $key = $this->normalizeLocationKey($value);
        if ($key === '') {
            return null;
        }

        foreach ($this->repository->search() as $unit) {
            if (!$this->isChamberStorageUnit($unit)) {
                continue;
            }

            if (in_array($key, $this->unitLocationKeys($unit), true)) {
                return $unit;
            }
        }

        return $this->findUnitByLocationValue($value, self::STORAGE_LOCATION_TYPES);
    }

    private function currentLoadForUnit(FactoryUnit $unit): float
    {
        $stockByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentStockByStorageLocation());
        $crystallizationStockByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentCrystallizationStockByStorageLocation());
        $initialStockByLocationKey = $this->loadRowsByLocation($this->storageMovementRepository->currentInitialStockByStorageLocation());
        $load = $this->stockForUnit($unit, $stockByLocationKey);
        $load += $this->stockForUnit($unit, $crystallizationStockByLocationKey);
        $load += $this->stockForUnit($unit, $initialStockByLocationKey);

        if ($unit->getType() === FactoryUnit::TYPE_TUNNEL) {
            $tunnelByLocationKey = $this->loadRowsByLocation($this->fishReceptionRepository->currentLoadByTunnel());
            $load += $this->stockForUnit($unit, $tunnelByLocationKey);
        }

        return $load;
    }

    /** @return array<string, mixed> */
    private function capacityDiagnosticPayload(?FactoryUnit $unit, float $requestedQuantity, float $currentLoad, bool $canSubmit, string $tone, string $title, string $message): array
    {
        $capacity = $unit instanceof FactoryUnit ? (float) $unit->getCapacityKg() : 0.0;
        $projectedLoad = $currentLoad + max(0.0, $requestedQuantity);
        $freeBefore = $capacity > 0.001 ? max(0.0, $capacity - $currentLoad) : 0.0;
        $freeAfter = $capacity > 0.001 ? max(0.0, $capacity - $projectedLoad) : 0.0;
        $percentAfter = $capacity > 0.001 ? ($projectedLoad / $capacity) * 100 : 0.0;

        return [
            'canSubmit' => $canSubmit,
            'tone' => $tone,
            'title' => $title,
            'message' => $message,
            'unitName' => $unit instanceof FactoryUnit ? $unit->getDisplayName() : null,
            'load' => round($currentLoad, 3),
            'loadDisplay' => $this->formatKg($currentLoad),
            'requested' => round(max(0.0, $requestedQuantity), 3),
            'requestedDisplay' => $this->formatKg(max(0.0, $requestedQuantity)),
            'projectedLoad' => round($projectedLoad, 3),
            'projectedLoadDisplay' => $this->formatKg($projectedLoad),
            'capacity' => round($capacity, 3),
            'capacityDisplay' => $capacity > 0.001 ? $this->formatKg($capacity) : 'Non renseignee',
            'freeBefore' => round($freeBefore, 3),
            'freeBeforeDisplay' => $capacity > 0.001 ? $this->formatKg($freeBefore) : '-',
            'freeAfter' => round($freeAfter, 3),
            'freeAfterDisplay' => $capacity > 0.001 ? $this->formatKg($freeAfter) : '-',
            'percentAfter' => round($percentAfter, 1),
            'percentAfterDisplay' => $capacity > 0.001 ? number_format(min(999.0, $percentAfter), 1, ',', ' ').' %' : '-',
        ];
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

        return $load > 0.001 ? 'Occupee' : 'Vide';
    }

    private function spaceProgress(float $percent, float $load): int
    {
        if ($load <= 0.001 || $percent <= 0.0) {
            return 0;
        }

        return min(100, max(1, (int) ceil($percent)));
    }

    private function formatPercent(float $percent): string
    {
        $percent = min(999.0, max(0.0, $percent));
        if ($percent <= 0.0) {
            return '0 %';
        }

        if ($percent < 0.1) {
            return '< 0,1 %';
        }

        if ($percent < 10.0) {
            return rtrim(rtrim(number_format($percent, 1, ',', ' '), '0'), ',').' %';
        }

        return number_format($percent, 0, ',', ' ').' %';
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

        $this->applyFactoryDefaults($unit);

        $existing = $this->repository->findOneBy(['code' => $unit->getCode()]);
        if ($existing instanceof FactoryUnit && $existing->getId() !== $unit->getId()) {
            throw new \DomainException('Cette référence usine existe déjà.');
        }

        if ($unit->getStatus() !== FactoryUnit::STATUS_OPERATIONAL) {
            $unit->setIsSaturated(false);
        }
    }

    private function applyFactoryDefaults(FactoryUnit $unit): void
    {
        $this->applyDimensionDefaults($unit);
        $this->applyTemperatureDefaults($unit);
    }

    private function applyDimensionDefaults(FactoryUnit $unit): void
    {
        if ((float) $unit->getHeightMeters() <= 0.001) {
            $unit->setHeightMeters(3);
        }

        $capacity = (float) $unit->getCapacityKg();
        if ($capacity <= 0.001) {
            return;
        }

        $length = (float) $unit->getLengthMeters();
        $width = (float) $unit->getWidthMeters();
        if ($length > 0.001 && $width > 0.001) {
            return;
        }

        $surface = max(1.0, $capacity / $this->capacityKgPerM2($unit));
        if ($length <= 0.001 && $width <= 0.001) {
            $ratio = self::DIMENSION_RATIO[$unit->getType()] ?? self::DIMENSION_RATIO[FactoryUnit::TYPE_OTHER];
            $width = sqrt($surface / $ratio);
            $length = $surface / $width;
        } elseif ($length <= 0.001) {
            $length = $surface / max(0.01, $width);
        } elseif ($width <= 0.001) {
            $width = $surface / max(0.01, $length);
        }

        $unit
            ->setLengthMeters(round($length, 2))
            ->setWidthMeters(round($width, 2));
    }

    private function applyTemperatureDefaults(FactoryUnit $unit): void
    {
        $defaults = self::TEMPERATURE_DEFAULTS[$unit->getType()] ?? self::TEMPERATURE_DEFAULTS[FactoryUnit::TYPE_OTHER];

        if ($unit->getTargetTemperature() === null) {
            $unit->setTargetTemperature($defaults['target']);
        }

        if ($unit->getMinTemperature() === null) {
            $unit->setMinTemperature($defaults['min']);
        }

        if ($unit->getMaxTemperature() === null) {
            $unit->setMaxTemperature($defaults['max']);
        }
    }

    private function capacityKgPerM2(FactoryUnit $unit): float
    {
        return self::CAPACITY_KG_PER_M2[$unit->getType()] ?? self::CAPACITY_KG_PER_M2[FactoryUnit::TYPE_OTHER];
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
