<?php

namespace App\Repository;

use App\Entity\InventoryItem;
use App\Entity\InventoryMovement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryMovement> */
class InventoryMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryMovement::class);
    }

    /** @param array<string, mixed> $filters */
    public function searchVisible(User $actor, bool $viewAll, array $filters = [], int $limit = 80): array
    {
        return $this->visibleQuery($actor, $viewAll, $filters)
            ->orderBy('m.movementDate', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)))
            ->getQuery()
            ->getResult();
    }

    /** @return list<InventoryMovement> */
    public function recentVisible(User $actor, bool $viewAll, int $limit = 10): array
    {
        return $this->searchVisible($actor, $viewAll, [], $limit);
    }

    /** @return list<InventoryMovement> */
    public function historyForItem(InventoryItem $item): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.fromSite', 'fromSite')
            ->addSelect('fromSite')
            ->leftJoin('m.fromLocation', 'fromLocation')
            ->addSelect('fromLocation')
            ->leftJoin('m.toSite', 'toSite')
            ->addSelect('toSite')
            ->leftJoin('m.toLocation', 'toLocation')
            ->addSelect('toLocation')
            ->leftJoin('m.responsibleUser', 'responsible')
            ->addSelect('responsible')
            ->leftJoin('m.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('m.item = :item')
            ->setParameter('item', $item)
            ->orderBy('m.movementDate', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function visibleQuery(User $actor, bool $viewAll, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.item', 'i')
            ->addSelect('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->leftJoin('i.responsibleUser', 'responsible')
            ->addSelect('responsible')
            ->leftJoin('m.toLocation', 'toLocation')
            ->addSelect('toLocation')
            ->leftJoin('m.toSite', 'toSite')
            ->addSelect('toSite')
            ->andWhere('i.isDeleted = false');

        if (!$viewAll) {
            $qb
                ->andWhere('(i.responsibleUser = :actor OR i.createdBy = :actor)')
                ->setParameter('actor', $actor);
        }

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(i.reference) LIKE :query OR LOWER(i.name) LIKE :query OR LOWER(COALESCE(m.reason, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $qb->andWhere('m.movementType = :type')->setParameter('type', $type);
        }

        return $qb;
    }
}
