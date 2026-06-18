<?php

namespace App\Repository;

use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryItem> */
class InventoryItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryItem::class);
    }

    /** @param array<string, mixed> $filters */
    public function searchVisible(User $actor, bool $viewAll, array $filters = [], int $page = 1, int $limit = 24): array
    {
        $page = max(1, $page);
        $limit = max(1, min(80, $limit));
        $qb = $this->visibleQuery($actor, $viewAll, $filters)
            ->orderBy('i.isActive', 'DESC')
            ->addOrderBy('i.name', 'ASC');

        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);

        $items = $qb
            ->select('DISTINCT i, c, s, l, responsible')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function countVisible(User $actor, bool $viewAll, array $filters = []): int
    {
        return (int) $this->visibleQuery($actor, $viewAll, $filters)
            ->select('COUNT(DISTINCT i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<InventoryItem> */
    public function recentVisible(User $actor, bool $viewAll, int $limit = 8): array
    {
        return $this->visibleQuery($actor, $viewAll, ['active' => 'active'])
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{label: string, value: int}> */
    public function groupByStatus(User $actor, bool $viewAll): array
    {
        return $this->groupVisible($actor, $viewAll, 'i.status');
    }

    /** @return list<array{label: string, value: int}> */
    public function groupByCategory(User $actor, bool $viewAll): array
    {
        $rows = $this->visibleQuery($actor, $viewAll, ['active' => 'active'])
            ->select('COALESCE(c.name, :empty) AS label, COUNT(DISTINCT i.id) AS value')
            ->setParameter('empty', 'Sans catégorie')
            ->groupBy('label')
            ->orderBy('value', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $rows);
    }

    /** @return list<array{label: string, value: int}> */
    public function groupBySite(User $actor, bool $viewAll): array
    {
        $rows = $this->visibleQuery($actor, $viewAll, ['active' => 'active'])
            ->select('COALESCE(s.name, :empty) AS label, COUNT(DISTINCT i.id) AS value')
            ->setParameter('empty', 'Sans site')
            ->groupBy('label')
            ->orderBy('value', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $rows);
    }

    /** @return list<array{label: string, value: int}> */
    public function groupByLogisticsStatus(User $actor, bool $viewAll): array
    {
        return $this->groupVisible($actor, $viewAll, 'i.logisticsStatus');
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @return array<int, int> */
    public function countActiveBySite(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.site) AS siteId, COUNT(i.id) AS total')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.site IS NOT NULL')
            ->groupBy('i.site')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['siteId']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<int, int> */
    public function countActiveByLocation(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.location) AS locationId, COUNT(i.id) AS total')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.location IS NOT NULL')
            ->groupBy('i.location')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['locationId']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAttachedToSite(InventorySite $site): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.id)')
            ->leftJoin('i.location', 'l')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.site = :site OR l.site = :site')
            ->setParameter('site', $site)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAttachedToLocation(InventoryLocation $location): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<InventoryItem> */
    public function attachedToSite(InventorySite $site, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.location', 'l')
            ->addSelect('l')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.site = :site OR l.site = :site')
            ->setParameter('site', $site)
            ->orderBy('i.name', 'ASC')
            ->addOrderBy('i.reference', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults(max(1, $limit));
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<InventoryItem> */
    public function attachedToLocation(InventoryLocation $location, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.location = :location')
            ->setParameter('location', $location)
            ->orderBy('i.name', 'ASC')
            ->addOrderBy('i.reference', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults(max(1, $limit));
        }

        return $qb->getQuery()->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function visibleQuery(User $actor, bool $viewAll, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->leftJoin('i.site', 's')
            ->addSelect('s')
            ->leftJoin('i.location', 'l')
            ->addSelect('l')
            ->leftJoin('i.responsibleUser', 'responsible')
            ->addSelect('responsible')
            ->leftJoin('i.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('i.isDeleted = false');

        if (!$viewAll) {
            $qb
                ->andWhere('(i.responsibleUser = :actor OR i.createdBy = :actor)')
                ->setParameter('actor', $actor);
        }

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(i.reference) LIKE :query OR LOWER(i.name) LIKE :query OR LOWER(COALESCE(i.dimensions, \'\')) LIKE :query OR LOWER(COALESCE(i.color, \'\')) LIKE :query OR LOWER(COALESCE(i.brand, \'\')) LIKE :query OR LOWER(COALESCE(i.model, \'\')) LIKE :query OR LOWER(COALESCE(i.serialNumber, \'\')) LIKE :query OR LOWER(COALESCE(c.name, \'\')) LIKE :query OR LOWER(COALESCE(s.name, \'\')) LIKE :query OR LOWER(COALESCE(l.name, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        foreach (['status', 'condition', 'ownershipType', 'logisticsStatus'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $qb->andWhere(sprintf('i.%s = :%s', $field, $field))->setParameter($field, $value);
            }
        }

        foreach (['category' => 'c', 'site' => 's', 'location' => 'l', 'responsible' => 'responsible'] as $filter => $alias) {
            $value = (int) ($filters[$filter] ?? 0);
            if ($value > 0) {
                $qb->andWhere(sprintf('%s.id = :%s', $alias, $filter))->setParameter($filter, $value);
            }
        }

        $active = (string) ($filters['active'] ?? 'active');
        if ($active === 'archived') {
            $qb->andWhere('i.isActive = false');
        } elseif ($active !== 'all') {
            $qb->andWhere('i.isActive = true');
        }
    }

    /** @return list<array{label: string, value: int}> */
    private function groupVisible(User $actor, bool $viewAll, string $field): array
    {
        $rows = $this->visibleQuery($actor, $viewAll, ['active' => 'active'])
            ->select(sprintf('%s AS label, COUNT(DISTINCT i.id) AS value', $field))
            ->groupBy('label')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $rows);
    }
}
