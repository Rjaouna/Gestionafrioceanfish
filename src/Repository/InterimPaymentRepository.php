<?php

namespace App\Repository;

use App\Entity\InterimPayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimPayment> */
class InterimPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimPayment::class);
    }

    /** @param array<string, mixed> $filters @return list<InterimPayment> */
    public function search(array $filters = [], int $page = 1, int $perPage = 25): array
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
            ->select('COUNT(DISTINCT p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters @return array{amount: float, count: int, workers: int, pending: float, paid: float, cancelled: float} */
    public function totals(array $filters = []): array
    {
        $row = $this->buildSearchQuery($filters)
            ->select('COALESCE(SUM(p.amount), 0) AS amount')
            ->addSelect('COUNT(DISTINCT p.id) AS countRows')
            ->addSelect('COUNT(DISTINCT w.id) AS workers')
            ->addSelect('COALESCE(SUM(CASE WHEN p.status = :pending THEN p.amount ELSE 0 END), 0) AS pending')
            ->addSelect('COALESCE(SUM(CASE WHEN p.status = :paid THEN p.amount ELSE 0 END), 0) AS paid')
            ->addSelect('COALESCE(SUM(CASE WHEN p.status = :cancelled THEN p.amount ELSE 0 END), 0) AS cancelled')
            ->setParameter('pending', InterimPayment::STATUS_PENDING)
            ->setParameter('paid', InterimPayment::STATUS_PAID)
            ->setParameter('cancelled', InterimPayment::STATUS_CANCELLED)
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleResult();

        return [
            'amount' => (float) ($row['amount'] ?? 0),
            'count' => (int) ($row['countRows'] ?? 0),
            'workers' => (int) ($row['workers'] ?? 0),
            'pending' => (float) ($row['pending'] ?? 0),
            'paid' => (float) ($row['paid'] ?? 0),
            'cancelled' => (float) ($row['cancelled'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function buildSearchQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('p')
            ->leftJoin('p.worker', 'w')
            ->addSelect('w')
            ->orderBy('p.paymentDate', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(COALESCE(p.reference, \'\')) LIKE :query
                    OR LOWER(COALESCE(p.note, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.lastName, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.firstName, \'\')) LIKE :query
                    OR LOWER(CONCAT(COALESCE(w.lastName, \'\'), \' \', COALESCE(w.firstName, \'\'))) LIKE :query
                    OR LOWER(COALESCE(w.registrationNumber, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.position, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (($filters['workerId'] ?? '') !== '') {
            $builder
                ->andWhere('w.id = :workerId')
                ->setParameter('workerId', (int) $filters['workerId']);
        }

        if (($filters['status'] ?? '') !== '') {
            $builder
                ->andWhere('p.status = :status')
                ->setParameter('status', (string) $filters['status']);
        }

        if (($filters['paymentMethod'] ?? '') !== '') {
            $builder
                ->andWhere('p.paymentMethod = :paymentMethod')
                ->setParameter('paymentMethod', (string) $filters['paymentMethod']);
        }

        if (($filters['dateFrom'] ?? '') !== '') {
            $builder
                ->andWhere('p.paymentDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (($filters['dateTo'] ?? '') !== '') {
            $builder
                ->andWhere('p.paymentDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        if (($filters['periodFrom'] ?? '') !== '') {
            $builder
                ->andWhere('(p.periodTo IS NULL OR p.periodTo >= :periodFrom)')
                ->setParameter('periodFrom', new \DateTimeImmutable((string) $filters['periodFrom']));
        }

        if (($filters['periodTo'] ?? '') !== '') {
            $builder
                ->andWhere('(p.periodFrom IS NULL OR p.periodFrom <= :periodTo)')
                ->setParameter('periodTo', new \DateTimeImmutable((string) $filters['periodTo']));
        }

        return $builder;
    }
}
