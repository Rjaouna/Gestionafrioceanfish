<?php

namespace App\Repository;

use App\Entity\GeneratedContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GeneratedContract> */
final class GeneratedContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeneratedContract::class);
    }

    /** @param array<string, mixed> $filters */
    public function search(array $filters, int $page, int $perPage): array
    {
        return $this->buildSearchQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countSearch(array $filters): int
    {
        return (int) $this->buildSearchQuery($filters)
            ->select('COUNT(DISTINCT c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    private function buildSearchQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->leftJoin('c.lastGeneratedBy', 'generator')
            ->addSelect('creator', 'generator')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.contractDate', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(c.reference) LIKE :query OR LOWER(c.clientCompanyName) LIKE :query OR LOWER(c.representativeName) LIKE :query OR LOWER(c.representativeIdNumber) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $type = (string) ($filters['type'] ?? '');
        if (isset(GeneratedContract::TYPE_LABELS[$type])) {
            $builder->andWhere('c.contractType = :type')->setParameter('type', $type);
        }

        $status = (string) ($filters['status'] ?? '');
        if (isset(GeneratedContract::STATUS_LABELS[$status])) {
            $builder->andWhere('c.status = :status')->setParameter('status', $status);
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('c.contractDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('c.contractDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $builder;
    }
}
