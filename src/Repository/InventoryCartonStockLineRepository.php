<?php

namespace App\Repository;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryCartonStockLine> */
final class InventoryCartonStockLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCartonStockLine::class);
    }

    public function nextPosition(InventoryCartonStock $stock): int
    {
        $position = $this->createQueryBuilder('l')
            ->select('COALESCE(MAX(l.position), 0)')
            ->andWhere('l.stock = :stock')
            ->setParameter('stock', $stock)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $position + 10;
    }

    public function findOneForFixture(InventoryCartonStock $stock, ?string $groupName, string $reference, string $lineType): ?InventoryCartonStockLine
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.stock = :stock')
            ->andWhere('LOWER(COALESCE(l.groupName, \'\')) = :groupName')
            ->andWhere('LOWER(l.reference) = :reference')
            ->andWhere('l.lineType = :lineType')
            ->setParameter('stock', $stock)
            ->setParameter('groupName', mb_strtolower(trim((string) $groupName)))
            ->setParameter('reference', mb_strtolower(trim($reference)))
            ->setParameter('lineType', $lineType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
