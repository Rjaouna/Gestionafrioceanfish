<?php

namespace App\Repository;

use App\Entity\InventorySite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventorySite> */
class InventorySiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventorySite::class);
    }

    /** @return list<InventorySite> */
    public function activeList(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.locations', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('l')
            ->andWhere('s.isActive = true')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByNameInsensitive(string $name): ?InventorySite
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.name) = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
