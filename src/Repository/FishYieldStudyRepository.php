<?php

namespace App\Repository;

use App\Entity\FishYieldStudy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FishYieldStudy> */
class FishYieldStudyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FishYieldStudy::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<FishYieldStudy>
     */
    public function searchWithFilters(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        return $this->buildFilteredQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countWithFilters(array $filters = []): int
    {
        return (int) $this->buildFilteredQuery($filters)
            ->select('COUNT(DISTINCT s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByReferencePrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['clientName', 'speciesName', 'operatorName'], true)) {
            throw new \InvalidArgumentException('Champ filtre etude rendement invalide.');
        }

        $rows = $this->createQueryBuilder('s')
            ->select(sprintf('DISTINCT s.%s AS value', $field))
            ->andWhere('s.isDeleted = false')
            ->andWhere(sprintf('s.%s IS NOT NULL', $field))
            ->andWhere(sprintf("s.%s <> ''", $field))
            ->orderBy(sprintf('s.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_column($rows, 'value'),
        )));
    }

    /** @param array<string, mixed> $filters */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('s')
            ->leftJoin('s.createdBy', 'creator')
            ->leftJoin('s.updatedBy', 'updater')
            ->addSelect('creator', 'updater')
            ->andWhere('s.isDeleted = false');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(s.reference) LIKE :query
                    OR LOWER(COALESCE(s.clientName, \'\')) LIKE :query
                    OR LOWER(s.speciesName) LIKE :query
                    OR LOWER(COALESCE(s.mixedFishName, \'\')) LIKE :query
                    OR LOWER(COALESCE(s.operatorName, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('s.studyDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('s.studyDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        if (!empty($filters['clientName'])) {
            $builder
                ->andWhere('s.clientName = :clientName')
                ->setParameter('clientName', (string) $filters['clientName']);
        }

        if (!empty($filters['speciesName'])) {
            $builder
                ->andWhere('s.speciesName = :speciesName')
                ->setParameter('speciesName', (string) $filters['speciesName']);
        }

        $sortMap = [
            'date' => 's.studyDate',
            'reference' => 's.reference',
            'client' => 's.clientName',
            'species' => 's.speciesName',
        ];
        $sort = $sortMap[(string) ($filters['sort'] ?? 'date')] ?? 's.studyDate';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return $builder
            ->orderBy($sort, $direction)
            ->addOrderBy('s.id', 'DESC');
    }
}
