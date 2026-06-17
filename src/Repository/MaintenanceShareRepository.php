<?php

namespace App\Repository;

use App\Entity\MaintenanceShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceShare> */
class MaintenanceShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceShare::class);
    }

    public function findFor(string $itemType, int $itemId, User $user): ?MaintenanceShare
    {
        return $this->findOneBy([
            'itemType' => $itemType,
            'itemId' => $itemId,
            'user' => $user,
        ]);
    }

    /** @return list<MaintenanceShare> */
    public function findForItem(string $itemType, int $itemId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.user', 'u')
            ->addSelect('u')
            ->andWhere('s.itemType = :itemType')
            ->andWhere('s.itemId = :itemId')
            ->setParameter('itemType', $itemType)
            ->setParameter('itemId', $itemId)
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, true> */
    public function findActiveItemIdsForUser(string $itemType, User $user): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.itemId AS itemId')
            ->andWhere('s.itemType = :itemType')
            ->andWhere('s.user = :user')
            ->andWhere('s.isActive = true')
            ->andWhere('s.canView = true')
            ->setParameter('itemType', $itemType)
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $ids[(int) $row['itemId']] = true;
        }

        return $ids;
    }

    /**
     * @param list<int> $itemIds
     *
     * @return array<int, int>
     */
    public function countActiveForItems(string $itemType, array $itemIds): array
    {
        $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('s.itemId AS itemId, COUNT(s.id) AS shareCount')
            ->andWhere('s.itemType = :itemType')
            ->andWhere('s.itemId IN (:itemIds)')
            ->andWhere('s.isActive = true')
            ->andWhere('s.canView = true')
            ->setParameter('itemType', $itemType)
            ->setParameter('itemIds', $itemIds)
            ->groupBy('s.itemId')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['itemId']] = (int) $row['shareCount'];
        }

        return $counts;
    }

    public function hasActiveShare(string $itemType, int $itemId, User $user): bool
    {
        $share = $this->findFor($itemType, $itemId, $user);

        return $share instanceof MaintenanceShare && $share->isActive() && $share->canView();
    }
}
