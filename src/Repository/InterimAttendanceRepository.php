<?php

namespace App\Repository;

use App\Entity\InterimAttendance;
use App\Entity\InterimWorker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimAttendance> */
class InterimAttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimAttendance::class);
    }

    public function findHourlyForWorkerAndDate(InterimWorker $worker, \DateTimeImmutable $date): ?InterimAttendance
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.worker = :worker')
            ->andWhere('a.mode = :mode')
            ->andWhere('a.attendanceDate = :date')
            ->setParameter('worker', $worker)
            ->setParameter('mode', InterimAttendance::MODE_HOURLY)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<InterimAttendance>
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 24): array
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
            ->select('COUNT(DISTINCT a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function totals(array $filters = []): array
    {
        $row = $this->buildSearchQuery($filters)
            ->select('COALESCE(SUM(a.totalHours), 0) AS hours, COALESCE(SUM(a.totalAmount), 0) AS amount, COUNT(DISTINCT a.id) AS countRows, COALESCE(SUM(CASE WHEN a.mode = :taskMode AND a.taskType = :cleaningType THEN a.taskQuantity * 10 WHEN a.mode = :taskMode AND a.taskType = :boxingType THEN a.taskQuantity ELSE 0 END), 0) AS taskKg')
            ->resetDQLPart('orderBy')
            ->setParameter('taskMode', InterimAttendance::MODE_TASK)
            ->setParameter('cleaningType', InterimAttendance::TASK_CLEANING_ANCHOVY)
            ->setParameter('boxingType', InterimAttendance::TASK_BOXING_FILETS)
            ->getQuery()
            ->getSingleResult();

        return [
            'hours' => (float) ($row['hours'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
            'count' => (int) ($row['countRows'] ?? 0),
            'taskKg' => (float) ($row['taskKg'] ?? 0),
        ];
    }

    /** @return list<InterimAttendance> */
    public function findForWorkerMonth(InterimWorker $worker, \DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $month->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->andWhere('a.worker = :worker')
            ->andWhere('a.attendanceDate BETWEEN :start AND :end')
            ->setParameter('worker', $worker)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.attendanceDate', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<InterimAttendance> */
    public function findForWorkerDate(InterimWorker $worker, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.worker = :worker')
            ->andWhere('a.attendanceDate = :date')
            ->setParameter('worker', $worker)
            ->setParameter('date', $date)
            ->orderBy('a.mode', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array<string, mixed>> */
    public function journalByWorker(\DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        return $this->createQueryBuilder('a')
            ->select('
                w.id AS workerId,
                w.lastName AS lastName,
                w.firstName AS firstName,
                w.registrationNumber AS registrationNumber,
                COALESCE(SUM(a.totalHours), 0) AS hours,
                COALESCE(SUM(CASE WHEN a.mode = :taskMode AND a.taskType = :cleaningType THEN a.taskQuantity * 10 ELSE 0 END), 0) AS cleaningKg,
                COALESCE(SUM(CASE WHEN a.mode = :taskMode AND a.taskType = :boxingType THEN a.taskQuantity ELSE 0 END), 0) AS boxingKg,
                COUNT(a.id) AS lineCount
            ')
            ->innerJoin('a.worker', 'w')
            ->andWhere('w.isDeleted = false')
            ->andWhere('a.attendanceDate BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('taskMode', InterimAttendance::MODE_TASK)
            ->setParameter('cleaningType', InterimAttendance::TASK_CLEANING_ANCHOVY)
            ->setParameter('boxingType', InterimAttendance::TASK_BOXING_FILETS)
            ->groupBy('w.id, w.lastName, w.firstName, w.registrationNumber')
            ->orderBy('w.lastName', 'ASC')
            ->addOrderBy('w.firstName', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @param array<string, mixed> $filters */
    private function buildSearchQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('a')
            ->innerJoin('a.worker', 'w')
            ->addSelect('w')
            ->andWhere('w.isDeleted = false')
            ->orderBy('a.attendanceDate', 'DESC')
            ->addOrderBy('w.lastName', 'ASC')
            ->addOrderBy('w.firstName', 'ASC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(w.lastName) LIKE :query
                    OR LOWER(w.firstName) LIKE :query
                    OR LOWER(CONCAT(w.lastName, \' \', w.firstName)) LIKE :query
                    OR LOWER(COALESCE(w.registrationNumber, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.position, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (($filters['mode'] ?? '') !== '') {
            $builder
                ->andWhere('a.mode = :mode')
                ->setParameter('mode', (string) $filters['mode']);
        }

        if (($filters['dateFrom'] ?? '') !== '') {
            $builder
                ->andWhere('a.attendanceDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (($filters['dateTo'] ?? '') !== '') {
            $builder
                ->andWhere('a.attendanceDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $builder;
    }
}
