<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\DailyProductionCost;
use App\Entity\DailyProductionCostChargeLine;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\DailyProductionCostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class DailyProductionCostService
{
    public function __construct(
        private DailyProductionCostRepository $repository,
        private CoutRevientChargeConfigRepository $chargeConfigRepository,
        private EntityManagerInterface $entityManager,
        private CoutRevientPermissionService $permission,
        private DailyProductionCostCalculatorService $calculator,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<DailyProductionCost>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>, totals: array<string, mixed>}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $this->assertAccess($actor);
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $total = $this->repository->countWithFilters($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->search($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'totals' => $this->repository->totals($filters),
        ];
    }

    /** @param array<int|string, mixed> $chargeRows */
    public function create(DailyProductionCost $cost, User $actor, array $chargeRows = []): DailyProductionCost
    {
        $this->assertAccess($actor);
        $this->prepare($cost);
        $this->syncChargeLines($cost, $chargeRows, $actor);
        $this->calculator->calculate($cost);
        $cost->setCreatedBy($actor);

        $this->entityManager->persist($cost);
        $this->entityManager->flush();

        return $cost;
    }

    /** @param array<int|string, mixed> $chargeRows */
    public function update(DailyProductionCost $cost, User $actor, array $chargeRows = []): DailyProductionCost
    {
        $this->assertAccess($actor);
        $this->prepare($cost);
        $this->syncChargeLines($cost, $chargeRows, $actor);
        $this->calculator->calculate($cost);
        $cost->setUpdatedBy($actor);

        $this->entityManager->flush();

        return $cost;
    }

    /** @param array<string, mixed> $filters */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
        ];

        foreach (['dateFrom', 'dateTo'] as $dateKey) {
            if ($normalized[$dateKey] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$dateKey])) {
                $normalized[$dateKey] = '';
            }
        }

        return $normalized;
    }

    private function prepare(DailyProductionCost $cost): void
    {
        if ($cost->getProductionDate() === null) {
            $cost->setProductionDate(new \DateTimeImmutable('today'));
        }

        if ($cost->getReference() === null || $cost->getReference() === '') {
            $cost->setReference($this->nextReference());
        }
    }

    /** @param array<int|string, mixed> $chargeRows */
    private function syncChargeLines(DailyProductionCost $cost, array $chargeRows, User $actor): void
    {
        foreach ($cost->getChargeLines()->toArray() as $line) {
            $cost->removeChargeLine($line);
            if ($line->getId() !== null) {
                $this->entityManager->remove($line);
            }
        }

        $sortOrder = 0;
        foreach ($chargeRows as $row) {
            if (!is_array($row) || !empty($row['remove'])) {
                continue;
            }

            $config = null;
            $configId = (int) ($row['chargeConfig'] ?? 0);
            if ($configId > 0) {
                $config = $this->chargeConfigRepository->find($configId);
            }

            $line = new DailyProductionCostChargeLine();
            if ($config instanceof CoutRevientChargeConfig) {
                $line->applyConfig($config);
            }

            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $calculationUnit = trim((string) ($row['calculationUnit'] ?? ''));

            if ($name !== '') {
                $line->setName($name);
            }

            if ($category !== '') {
                $line->setCategory($category);
            }

            if ($calculationUnit !== '') {
                $line->setCalculationUnit($calculationUnit);
            }

            $line
                ->setUnitCost($row['unitCost'] ?? $line->getUnitCost())
                ->setQuantity($row['quantity'] ?? 0)
                ->setNote((string) ($row['note'] ?? ''))
                ->setSortOrder(++$sortOrder)
                ->setCreatedBy($actor)
                ->recalculate();

            if ($line->getName() === '' || (float) $line->getQuantity() <= 0) {
                continue;
            }

            $cost->addChargeLine($line);
        }
    }

    private function nextReference(): string
    {
        $prefix = sprintf('CJ-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByReferencePrefix($prefix) + 1;

        do {
            $reference = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['reference' => $reference]) instanceof DailyProductionCost);

        return $reference;
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }
}
