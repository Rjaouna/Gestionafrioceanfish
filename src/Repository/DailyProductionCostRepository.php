<?php

namespace App\Repository;

use App\Entity\DailyProductionCost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DailyProductionCost> */
class DailyProductionCostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyProductionCost::class);
    }

    /** @param array<string, mixed> $filters */
    public function search(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        return $this->buildFilteredQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countWithFilters(array $filters = []): int
    {
        return (int) $this->buildFilteredQuery($filters)
            ->select('COUNT(DISTINCT d.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByReferencePrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function totals(array $filters = []): array
    {
        $items = $this->buildFilteredQuery($filters)->setMaxResults(1000)->getQuery()->getResult();

        return [
            'count' => count($items),
            'raw' => $this->sum($items, 'rawQuantityKg'),
            'finished' => $this->sum($items, 'finishedProductKg'),
            'waste' => $this->sum($items, 'wasteKg'),
            'loss' => $this->sum($items, 'lossKg'),
            'labor' => $this->sum($items, 'laborTotal'),
            'packaging' => $this->sum($items, 'packagingTotal'),
            'charges' => $this->sum($items, 'chargesTotal'),
            'total' => $this->sum($items, 'totalCost'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('d')
            ->leftJoin('d.createdBy', 'creator')
            ->leftJoin('d.updatedBy', 'updater')
            ->addSelect('creator', 'updater')
            ->orderBy('d.productionDate', 'DESC')
            ->addOrderBy('d.id', 'DESC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(d.reference) LIKE :query OR LOWER(d.productName) LIKE :query OR LOWER(COALESCE(d.responsible, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('d.productionDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('d.productionDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $builder;
    }

    /** @param list<DailyProductionCost> $items */
    private function sum(array $items, string $property): float
    {
        return array_sum(array_map(static fn (DailyProductionCost $item): float => $item->floatValue($property), $items));
    }
}
