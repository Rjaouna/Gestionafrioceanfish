<?php

namespace App\Repository;

use App\Entity\InterimWorker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimWorker> */
class InterimWorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimWorker::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<InterimWorker>
     */
    public function searchVisible(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        return $this->buildVisibleQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countVisible(array $filters = []): int
    {
        return (int) $this->buildVisibleQuery($filters)
            ->select('COUNT(DISTINCT w.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['position', 'workerType', 'familySituation', 'status', 'tempAgency'], true)) {
            throw new \InvalidArgumentException('Champ de filtre intérimaire invalide.');
        }

        $rows = $this->createQueryBuilder('w')
            ->select(sprintf('DISTINCT w.%s AS value', $field))
            ->andWhere('w.isDeleted = false')
            ->andWhere(sprintf('w.%s IS NOT NULL', $field))
            ->andWhere(sprintf("w.%s <> ''", $field))
            ->orderBy(sprintf('w.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_column($rows, 'value'),
        )));
    }

    public function countByRegistrationPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.registrationNumber LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<InterimWorker> */
    public function findForAttendanceSheet(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.isDeleted = false')
            ->andWhere('w.isActive = true')
            ->andWhere('w.status = :activeStatus')
            ->setParameter('activeStatus', InterimWorker::STATUS_ACTIVE)
            ->orderBy('w.lastName', 'ASC')
            ->addOrderBy('w.firstName', 'ASC')
            ->addOrderBy('w.registrationNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function buildVisibleQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('w')
            ->leftJoin('w.documents', 'documents')
            ->leftJoin('w.createdBy', 'creator')
            ->addSelect('documents', 'creator')
            ->andWhere('w.isDeleted = false')
            ->orderBy('w.hireDate', 'DESC')
            ->addOrderBy('w.lastName', 'ASC')
            ->addOrderBy('w.firstName', 'ASC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(w.lastName) LIKE :query
                    OR LOWER(w.firstName) LIKE :query
                    OR LOWER(CONCAT(w.lastName, \' \', w.firstName)) LIKE :query
                    OR LOWER(COALESCE(w.phone, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.cin, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.passportNumber, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.nationality, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.registrationNumber, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.position, \'\')) LIKE :query
                    OR LOWER(COALESCE(w.tempAgency, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['position'])) {
            $builder
                ->andWhere('w.position = :position')
                ->setParameter('position', (string) $filters['position']);
        }

        if (!empty($filters['workerType']) && isset(InterimWorker::TYPE_LABELS[(string) $filters['workerType']])) {
            $builder
                ->andWhere('w.workerType = :workerType')
                ->setParameter('workerType', (string) $filters['workerType']);
        }

        if (!empty($filters['familySituation']) && isset(InterimWorker::FAMILY_LABELS[(string) $filters['familySituation']])) {
            $builder
                ->andWhere('w.familySituation = :familySituation')
                ->setParameter('familySituation', (string) $filters['familySituation']);
        }

        if (!empty($filters['status']) && isset(InterimWorker::STATUS_LABELS[(string) $filters['status']])) {
            $builder
                ->andWhere('w.status = :status')
                ->setParameter('status', (string) $filters['status']);
        }

        if (!empty($filters['hireDate'])) {
            $builder
                ->andWhere('w.hireDate = :hireDate')
                ->setParameter('hireDate', new \DateTimeImmutable((string) $filters['hireDate']));
        }

        return $builder;
    }
}
