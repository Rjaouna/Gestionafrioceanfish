<?php

namespace App\Repository;

use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryLocation> */
class InventoryLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryLocation::class);
    }

    /** @return list<InventoryLocation> */
    public function activeList(?InventorySite $site = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.site', 's')
            ->addSelect('s')
            ->andWhere('l.isActive = true')
            ->andWhere('s.isActive = true')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('l.name', 'ASC');

        if ($site instanceof InventorySite) {
            $qb->andWhere('l.site = :site')->setParameter('site', $site);
        }

        return $qb->getQuery()->getResult();
    }
}
