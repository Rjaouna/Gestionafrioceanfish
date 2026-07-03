<?php

namespace App\Service\Inventory;

use App\Entity\InventoryItem;
use App\Entity\User;
use App\Repository\ConsumableStockItemRepository;
use App\Repository\InventoryItemRepository;
use App\Repository\InventoryMovementRepository;

final readonly class InventoryDashboardService
{
    public function __construct(
        private InventoryItemRepository $itemRepository,
        private InventoryMovementRepository $movementRepository,
        private InventoryAccessService $access,
        private ConsumableStockItemRepository $consumableRepository,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(User $actor): array
    {
        $viewAll = $this->access->canViewAll($actor);
        $active = $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'active']);
        $archived = $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'archived']);
        $unavailable = $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'active', 'status' => 'maintenance'])
            + $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'active', 'status' => 'lost'])
            + $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'active', 'status' => 'retired']);
        $assigned = $this->itemRepository->countVisible($actor, $viewAll, ['active' => 'active', 'status' => 'assigned']);
        $stockAlerts = $this->consumableRepository->countLowStock() + $this->consumableRepository->countOutOfStock();

        return [
            'cards' => [
                ['label' => 'Matériels actifs', 'value' => $active, 'icon' => 'bi-box-seam', 'tone' => 'primary'],
                ['label' => 'Affectés', 'value' => $assigned, 'icon' => 'bi-person-check', 'tone' => 'info'],
                ['label' => 'A surveiller', 'value' => $unavailable, 'icon' => 'bi-exclamation-triangle', 'tone' => $unavailable > 0 ? 'warning' : 'success'],
                ['label' => 'Alertes stock', 'value' => $stockAlerts, 'icon' => 'bi-basket', 'tone' => $stockAlerts > 0 ? 'danger' : 'success'],
                ['label' => 'Archivés', 'value' => $archived, 'icon' => 'bi-archive', 'tone' => 'secondary'],
            ],
            'status_chart' => $this->chart($this->labelStatuses($this->itemRepository->groupByStatus($actor, $viewAll))),
            'category_chart' => $this->chart($this->itemRepository->groupByCategory($actor, $viewAll)),
            'recent_items' => $this->itemRepository->recentVisible($actor, $viewAll, 8),
            'recent_movements' => $this->movementRepository->recentVisible($actor, $viewAll, 8),
            'archived' => $archived,
            'stock_alerts' => $stockAlerts,
            'stock_low_items' => $this->consumableRepository->lowStockItems(5),
        ];
    }

    /** @param list<array{label: string, value: int}> $rows */
    private function chart(array $rows): array
    {
        $max = max(1, ...array_map(static fn (array $row): int => $row['value'], $rows ?: [['value' => 1]]));

        return array_map(static fn (array $row): array => [
            'label' => $row['label'],
            'short_label' => mb_substr($row['label'], 0, 12),
            'value' => $row['value'],
            'display' => (string) $row['value'],
            'percent' => (int) round(($row['value'] / $max) * 100),
        ], $rows);
    }

    /** @param list<array{label: string, value: int}> $rows */
    private function labelStatuses(array $rows): array
    {
        $labels = array_flip(InventoryItem::STATUSES);

        return array_map(static fn (array $row): array => [
            'label' => $labels[$row['label']] ?? $row['label'],
            'value' => $row['value'],
        ], $rows);
    }
}
