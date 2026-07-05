<?php

namespace App\Repository;

use App\Entity\ConsumableStockItem;
use App\Entity\ConsumableStockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConsumableStockMovement> */
class ConsumableStockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConsumableStockMovement::class);
    }

    /** @return list<ConsumableStockMovement> */
    public function recent(int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.item', 'item')
            ->addSelect('item')
            ->leftJoin('m.performedBy', 'performedBy')
            ->addSelect('performedBy')
            ->orderBy('m.movementDate', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<ConsumableStockMovement> */
    public function forItem(ConsumableStockItem $item, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.performedBy', 'performedBy')
            ->addSelect('performedBy')
            ->andWhere('m.item = :item')
            ->setParameter('item', $item)
            ->orderBy('m.movementDate', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<ConsumableStockMovement> */
    public function chronologicalForItem(ConsumableStockItem $item): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.item = :item')
            ->setParameter('item', $item)
            ->orderBy('m.movementDate', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['supplier', 'recipient'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported consumable movement field "%s".', $field));
        }

        $rows = $this->createQueryBuilder('m')
            ->select(sprintf('DISTINCT m.%s AS value', $field))
            ->andWhere(sprintf('m.%s IS NOT NULL', $field))
            ->andWhere(sprintf("m.%s <> ''", $field))
            ->orderBy(sprintf('m.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), array_column($rows, 'value'))));
    }
}
