<?php

namespace App\Repository;

use App\Entity\InventoryCartonStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryCartonStock> */
final class InventoryCartonStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCartonStock::class);
    }

    /** @return list<InventoryCartonStock> */
    public function activeList(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.isActive = true')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<InventoryCartonStock> */
    public function listWithLines(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.lines', 'l')
            ->addSelect('l')
            ->orderBy('s.isActive', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('l.position', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, array{quantity: int, amount: float, amountLabel: string, lines: int}> */
    public function summariesByStock(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->leftJoin('s.lines', 'l')
            ->select('s.id AS stockId')
            ->addSelect('COALESCE(SUM(CASE WHEN l.lineType = :item THEN l.quantity ELSE 0 END), 0) AS quantity')
            ->addSelect('COALESCE(SUM(CASE WHEN l.lineType <> :summary THEN l.totalAmount ELSE 0 END), 0) AS amount')
            ->addSelect('COUNT(l.id) AS lines')
            ->setParameter('item', 'item')
            ->setParameter('summary', 'summary')
            ->groupBy('s.id')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['stockId']] = [
                'quantity' => (int) $row['quantity'],
                'amount' => (float) $row['amount'],
                'amountLabel' => $this->formatDecimal((string) $row['amount']),
                'lines' => (int) $row['lines'],
            ];
        }

        return $summaries;
    }

    public function findOneByNameInsensitive(string $name): ?InventoryCartonStock
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.name) = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function formatDecimal(string $value): string
    {
        $formatted = number_format((float) $value, 3, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
