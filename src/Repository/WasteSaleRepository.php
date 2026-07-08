<?php

namespace App\Repository;

use App\Entity\WasteSale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<WasteSale> */
class WasteSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WasteSale::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<WasteSale>
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 18): array
    {
        return $this->buildSearchQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countSearch(array $filters = []): int
    {
        return (int) $this->buildSearchQuery($filters)
            ->select('COUNT(DISTINCT s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<WasteSale>
     */
    public function allForStats(array $filters = []): array
    {
        return $this->buildSearchQuery($filters)
            ->setMaxResults(5000)
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctBuyers(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('DISTINCT s.buyerName AS value')
            ->andWhere('s.isDeleted = false')
            ->andWhere('s.buyerName IS NOT NULL')
            ->andWhere("s.buyerName <> ''")
            ->orderBy('s.buyerName', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_column($rows, 'value'),
        )));
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    private function buildSearchQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('s')
            ->leftJoin('s.createdBy', 'creator')
            ->leftJoin('s.updatedBy', 'updater')
            ->addSelect('creator', 'updater')
            ->andWhere('s.isDeleted = false')
            ->orderBy('s.saleDate', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(s.reference) LIKE :query OR LOWER(s.buyerName) LIKE :query OR LOWER(COALESCE(s.notes, \'\')) LIKE :query OR LOWER(COALESCE(creator.email, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['buyerName'])) {
            $builder
                ->andWhere('s.buyerName = :buyerName')
                ->setParameter('buyerName', (string) $filters['buyerName']);
        }

        if (!empty($filters['paymentMethod']) && isset(WasteSale::PAYMENT_METHOD_LABELS[(string) $filters['paymentMethod']])) {
            $builder
                ->andWhere('s.paymentMethod = :paymentMethod')
                ->setParameter('paymentMethod', (string) $filters['paymentMethod']);
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('s.saleDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('s.saleDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $builder;
    }
}
