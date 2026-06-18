<?php

namespace App\Repository;

use App\Entity\InventoryCampaign;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryCampaign> */
class InventoryCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCampaign::class);
    }

    /** @param array<string, mixed> $filters */
    public function searchVisible(User $actor, bool $viewAll, array $filters = []): array
    {
        return $this->visibleQuery($actor, $viewAll, $filters)
            ->orderBy('c.startDate', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    private function visibleQuery(User $actor, bool $viewAll, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.site', 's')
            ->addSelect('s')
            ->leftJoin('c.responsibleUser', 'responsible')
            ->addSelect('responsible')
            ->leftJoin('c.lines', 'lines')
            ->addSelect('lines');

        if (!$viewAll) {
            $qb
                ->andWhere('(c.responsibleUser = :actor OR c.createdBy = :actor)')
                ->setParameter('actor', $actor);
        }

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(c.reference) LIKE :query OR LOWER(c.name) LIKE :query OR LOWER(COALESCE(s.name, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }
}
