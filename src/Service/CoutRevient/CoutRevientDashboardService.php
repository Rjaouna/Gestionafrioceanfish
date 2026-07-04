<?php

namespace App\Service\CoutRevient;

use App\Entity\User;
use App\Repository\CoutRevientRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CoutRevientDashboardService
{
    public function __construct(
        private CoutRevientRepository $repository,
        private CoutRevientPermissionService $permission,
        private CoutRevientService $coutRevientService,
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

        $filters = $this->coutRevientService->normalizeFilters($filters);
        $stats = $this->repository->getDashboardStats($filters);
        $costBreakdown = $this->repository->getCostBreakdown($filters);
        $marginByLot = $this->repository->getMarginByLot($filters);
        $rendementByLot = $this->repository->getRendementByLot($filters);
        $coutKgEvolution = $this->repository->getCoutKgEvolution($filters);
        $rentability = $this->repository->getRentabilityStats($filters);

        return [
            'filters' => $filters,
            'stats' => $stats,
            'charts' => [
                'cost_breakdown' => $this->pieChart($costBreakdown, ' dh'),
                'margin_by_lot' => $this->barChart($marginByLot, ' dh', 'success'),
                'rendement_by_lot' => $this->barChart($rendementByLot, ' %', 'primary'),
                'cout_kg_evolution' => $this->barChart($coutKgEvolution, ' dh/kg', 'info'),
                'rentability' => $this->pieChart($rentability, ''),
            ],
        ];
    }

    /**
     * @param list<array{label: string, value: int|float}> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function pieChart(array $rows, string $suffix): array
    {
        $total = array_sum(array_map(static fn (array $row): float => max(0.0, (float) $row['value']), $rows));
        $offset = 0.0;
        $chart = [];

        foreach ($rows as $row) {
            $value = max(0.0, (float) $row['value']);
            $share = $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
            $chart[] = [
                'label' => $row['label'],
                'value' => $value,
                'display' => $this->format($value, $suffix),
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $chart;
    }

    /**
     * @param list<array{label: string, value: int|float}> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function barChart(array $rows, string $suffix, string $tone): array
    {
        $max = max(1.0, ...array_map(static fn (array $row): float => abs((float) $row['value']), $rows ?: [['value' => 1]]));

        return array_map(function (array $row) use ($max, $suffix, $tone): array {
            $value = (float) $row['value'];

            return [
                'label' => $row['label'],
                'short_label' => $this->short($row['label']),
                'value' => $value,
                'display' => $this->format($value, $suffix),
                'percent' => min(100, max(4, (int) round((abs($value) / $max) * 100))),
                'tone' => $value < 0 ? 'danger' : $tone,
            ];
        }, $rows);
    }

    private function format(float $value, string $suffix): string
    {
        $decimals = str_contains($suffix, '%') || str_contains($suffix, '/kg') ? 2 : 0;

        return number_format($value, $decimals, ',', ' ').$suffix;
    }

    private function short(string $label): string
    {
        $label = trim($label);

        return mb_strlen($label) > 10 ? mb_substr($label, 0, 9).'...' : $label;
    }
}
