<?php

namespace App\Repository;

use App\Entity\InventoryItem;
use App\Entity\InventoryRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryRequest> */
class InventoryRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryRequest::class);
    }

    /** @param array<string, mixed> $filters */
    public function searchVisible(User $actor, bool $viewAll, array $filters = [], int $limit = 200): array
    {
        return $this->visibleQuery($actor, $viewAll, $filters)
            ->orderBy('r.status', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function countPendingVisible(User $actor, bool $viewAll): int
    {
        return (int) $this->visibleQuery($actor, $viewAll, ['status' => 'pending'])
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingVisibleByType(User $actor, bool $viewAll, string $type): int
    {
        return (int) $this->visibleQuery($actor, $viewAll, ['status' => 'pending', 'type' => $type])
            ->select('COUNT(DISTINCT r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingForItem(InventoryItem $item): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.item = :item')
            ->andWhere('r.status = :status')
            ->setParameter('item', $item)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    private function visibleQuery(User $actor, bool $viewAll, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.item', 'i')
            ->addSelect('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->leftJoin('i.responsibleUser', 'responsible')
            ->addSelect('responsible')
            ->leftJoin('r.fromSite', 'fromSite')
            ->addSelect('fromSite')
            ->leftJoin('r.fromLocation', 'fromLocation')
            ->addSelect('fromLocation')
            ->leftJoin('r.toSite', 'toSite')
            ->addSelect('toSite')
            ->leftJoin('r.toLocation', 'toLocation')
            ->addSelect('toLocation')
            ->leftJoin('r.createdBy', 'creator')
            ->addSelect('creator')
            ->leftJoin('r.validatedBy', 'validator')
            ->addSelect('validator')
            ->leftJoin('r.canceledBy', 'canceler')
            ->addSelect('canceler')
            ->leftJoin('r.resultItem', 'resultItem')
            ->addSelect('resultItem')
            ->andWhere('i.isDeleted = false');

        if (!$viewAll) {
            $qb
                ->andWhere('(i.responsibleUser = :actor OR i.createdBy = :actor OR r.createdBy = :actor)')
                ->setParameter('actor', $actor);
        }

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(i.reference) LIKE :query OR LOWER(i.name) LIKE :query OR LOWER(COALESCE(r.reason, \'\')) LIKE :query OR LOWER(COALESCE(r.notes, \'\')) LIKE :query OR LOWER(COALESCE(fromSite.name, \'\')) LIKE :query OR LOWER(COALESCE(toSite.name, \'\')) LIKE :query OR LOWER(COALESCE(fromLocation.name, \'\')) LIKE :query OR LOWER(COALESCE(toLocation.name, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $qb->andWhere('r.requestType = :type')->setParameter('type', $type);
        }

        $status = trim((string) ($filters['status'] ?? 'pending'));
        if ($status !== '') {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }
}
