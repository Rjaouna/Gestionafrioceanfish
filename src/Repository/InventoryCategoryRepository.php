<?php

namespace App\Repository;

use App\Entity\InventoryCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InventoryCategory> */
class InventoryCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryCategory::class);
    }

    /** @return list<InventoryCategory> */
    public function activeList(): array
    {
        return $this->findBy(['isActive' => true], ['name' => 'ASC']);
    }

    public function findOneByNameInsensitive(string $name): ?InventoryCategory
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
