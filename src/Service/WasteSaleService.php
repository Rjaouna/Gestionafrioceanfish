<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WasteSale;
use App\Repository\WasteSaleRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WasteSaleService
{
    public function __construct(
        private WasteSaleRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<WasteSale>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 18): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $total = $this->repository->countSearch($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->search($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function stats(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $items = $this->repository->allForStats($filters);
        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $weekEnd = $weekStart->modify('sunday this week');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        $stats = [
            'count' => count($items),
            'totalWeight' => 0.0,
            'totalAmount' => 0.0,
            'averageAmount' => 0.0,
            'today' => ['weight' => 0.0, 'amount' => 0.0, 'count' => 0],
            'week' => ['weight' => 0.0, 'amount' => 0.0, 'count' => 0],
            'month' => ['weight' => 0.0, 'amount' => 0.0, 'count' => 0],
            'byDay' => [],
            'byWeek' => [],
            'byMonth' => [],
            'paymentMethods' => [],
        ];

        foreach ($items as $item) {
            $date = $item->getSaleDate();
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }

            $weight = $item->weightKgValue();
            $amount = $item->totalAmountValue();
            $stats['totalWeight'] += $weight;
            $stats['totalAmount'] += $amount;

            if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
                $this->addSummary($stats['today'], $weight, $amount);
            }
            if ($date >= $weekStart && $date <= $weekEnd) {
                $this->addSummary($stats['week'], $weight, $amount);
            }
            if ($date >= $monthStart && $date <= $monthEnd) {
                $this->addSummary($stats['month'], $weight, $amount);
            }

            $dayKey = $date->format('Y-m-d');
            $weekKey = $date->format('o-\WW');
            $monthKey = $date->format('Y-m');
            $this->addGroupedSummary($stats['byDay'], $dayKey, $date->format('d/m/Y'), $weight, $amount);
            $this->addGroupedSummary($stats['byWeek'], $weekKey, 'Semaine '.$date->format('W/Y'), $weight, $amount);
            $this->addGroupedSummary($stats['byMonth'], $monthKey, $date->format('m/Y'), $weight, $amount);

            $paymentKey = $item->getPaymentMethod();
            if (!isset($stats['paymentMethods'][$paymentKey])) {
                $stats['paymentMethods'][$paymentKey] = [
                    'label' => $item->getPaymentMethodLabel(),
                    'weight' => 0.0,
                    'amount' => 0.0,
                    'count' => 0,
                ];
            }
            $this->addSummary($stats['paymentMethods'][$paymentKey], $weight, $amount);
        }

        $stats['averageAmount'] = $stats['count'] > 0 ? $stats['totalAmount'] / $stats['count'] : 0.0;
        foreach (['byDay', 'byWeek', 'byMonth', 'paymentMethods'] as $key) {
            $stats[$key] = array_values($stats[$key]);
            usort($stats[$key], static fn (array $a, array $b): int => strcmp((string) ($b['key'] ?? $b['label']), (string) ($a['key'] ?? $a['label'])));
        }

        return $stats;
    }

    public function create(WasteSale $sale, User $actor): WasteSale
    {
        $this->prepare($sale);
        $sale->setCreatedBy($actor);
        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        return $sale;
    }

    public function update(WasteSale $sale, User $actor): WasteSale
    {
        $this->prepare($sale);
        $sale->setUpdatedBy($actor);
        $sale->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $sale;
    }

    public function delete(WasteSale $sale, User $actor): void
    {
        $sale
            ->setIsDeleted(true)
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($actor)
            ->setDeleteReason('Suppression depuis le module ventes dechets.');
        $this->entityManager->flush();
    }

    /** @return list<string> */
    public function buyerChoices(): array
    {
        return $this->repository->distinctBuyers();
    }

    private function prepare(WasteSale $sale): void
    {
        if (!$sale->getReference()) {
            $sale->setReference($this->nextReference());
        }

        if (!$sale->getSaleDate()) {
            $sale->setSaleDate(new \DateTimeImmutable('today'));
        }

        $sale->setUnitPrice(WasteSale::DEFAULT_UNIT_PRICE);
        $sale->recalculateTotal();
        if (!$sale->getCreatedAt()) {
            $sale->setCreatedAt(new \DateTimeImmutable());
        }
    }

    private function nextReference(): string
    {
        $prefix = sprintf('VD-%s-', (new \DateTimeImmutable())->format('Y'));

        return sprintf('%s%04d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }

    /** @param array<string, mixed> $filters */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
            'buyerName' => trim((string) ($filters['buyerName'] ?? '')),
            'paymentMethod' => trim((string) ($filters['paymentMethod'] ?? '')),
        ];

        foreach (['dateFrom', 'dateTo'] as $dateField) {
            if ($normalized[$dateField] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$dateField])) {
                $normalized[$dateField] = '';
            }
        }

        if ($normalized['paymentMethod'] !== '' && !isset(WasteSale::PAYMENT_METHOD_LABELS[$normalized['paymentMethod']])) {
            $normalized['paymentMethod'] = '';
        }

        return $normalized;
    }

    /** @param array{weight: float, amount: float, count: int} $summary */
    private function addSummary(array &$summary, float $weight, float $amount): void
    {
        $summary['weight'] += $weight;
        $summary['amount'] += $amount;
        $summary['count']++;
    }

    /** @param array<string, array{key: string, label: string, weight: float, amount: float, count: int}> $groups */
    private function addGroupedSummary(array &$groups, string $key, string $label, float $weight, float $amount): void
    {
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'label' => $label,
                'weight' => 0.0,
                'amount' => 0.0,
                'count' => 0,
            ];
        }

        $this->addSummary($groups[$key], $weight, $amount);
    }
}
