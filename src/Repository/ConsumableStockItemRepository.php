<?php

namespace App\Repository;

use App\Entity\ConsumableStockItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConsumableStockItem> */
class ConsumableStockItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumableStockItem::class);
    }

    /** @param array<string, mixed> $filters @return list<ConsumableStockItem> */
    public function search(array $filters = []): array
    {
        return $this->filteredQuery($filters)
            ->orderBy('i.isActive', 'DESC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctCategories(): array
    {
        return $this->distinctValues('category');
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['category', 'unit', 'storageLocation', 'preferredSupplier', 'supplierPhone'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported consumable stock field "%s".', $field));
        }

        $rows = $this->createQueryBuilder('i')
            ->select(sprintf('DISTINCT i.%s AS value', $field))
            ->andWhere(sprintf('i.%s IS NOT NULL', $field))
            ->andWhere(sprintf("i.%s <> ''", $field))
            ->orderBy(sprintf('i.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), array_column($rows, 'value'))));
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOutOfStock(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.isActive = true')
            ->andWhere('i.quantity <= 0')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLowStock(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.isActive = true')
            ->andWhere('i.quantity > 0')
            ->andWhere('i.minimumQuantity > 0')
            ->andWhere('i.quantity <= i.minimumQuantity')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ConsumableStockItem> */
    public function lowStockItems(int $limit = 8): array
    {
        return $this->filteredQuery(['status' => 'alert', 'active' => 'active'])
            ->orderBy('i.quantity', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<ConsumableStockItem> */
    public function itemsBelowMinimum(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('i.isActive = true')
            ->andWhere('i.minimumQuantity > 0')
            ->andWhere('i.quantity < i.minimumQuantity')
            ->orderBy('i.preferredSupplier', 'ASC')
            ->addOrderBy('i.category', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{label: string, value: int}> */
    public function groupByCategory(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('COALESCE(i.category, :empty) AS label, COUNT(i.id) AS value')
            ->andWhere('i.isActive = true')
            ->setParameter('empty', 'Sans categorie')
            ->groupBy('label')
            ->orderBy('value', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $rows);
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.createdBy', 'creator')
            ->addSelect('creator');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(i.reference) LIKE :query OR LOWER(i.name) LIKE :query OR LOWER(COALESCE(i.category, \'\')) LIKE :query OR LOWER(COALESCE(i.storageLocation, \'\')) LIKE :query OR LOWER(COALESCE(i.preferredSupplier, \'\')) LIKE :query OR LOWER(COALESCE(i.supplierPhone, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $qb->andWhere('i.category = :category')->setParameter('category', $category);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === ConsumableStockItem::LEVEL_OUT) {
            $qb->andWhere('i.quantity <= 0');
        } elseif ($status === ConsumableStockItem::LEVEL_LOW) {
            $qb
                ->andWhere('i.quantity > 0')
                ->andWhere('i.minimumQuantity > 0')
                ->andWhere('i.quantity <= i.minimumQuantity');
        } elseif ($status === ConsumableStockItem::LEVEL_OK) {
            $qb->andWhere('i.quantity > 0')->andWhere('(i.minimumQuantity = 0 OR i.quantity > i.minimumQuantity)');
        } elseif ($status === 'alert') {
            $qb->andWhere('(i.quantity <= 0 OR (i.quantity > 0 AND i.minimumQuantity > 0 AND i.quantity <= i.minimumQuantity))');
        }

        $active = (string) ($filters['active'] ?? 'active');
        if ($active === 'archived') {
            $qb->andWhere('i.isActive = false');
        } elseif ($active !== 'all') {
            $qb->andWhere('i.isActive = true');
        }

        return $qb;
    }
}
