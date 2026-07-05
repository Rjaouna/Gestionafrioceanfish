<?php

namespace App\Repository;

use App\Entity\CoutRevientChargeConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CoutRevientChargeConfig> */
class CoutRevientChargeConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoutRevientChargeConfig::class);
    }

    /** @return list<CoutRevientChargeConfig> */
    public function search(string $query = '', bool $activeOnly = false): array
    {
        $builder = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->leftJoin('c.updatedBy', 'updater')
            ->leftJoin('c.factoryUnit', 'factoryUnit')
            ->addSelect('creator', 'updater', 'factoryUnit');

        if ($activeOnly) {
            $builder->andWhere('c.isActive = true');
        }

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.category) LIKE :query OR LOWER(COALESCE(c.description, \'\')) LIKE :query OR LOWER(COALESCE(factoryUnit.name, \'\')) LIKE :query OR LOWER(COALESCE(factoryUnit.code, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return $builder
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.category', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<CoutRevientChargeConfig> */
    public function findActive(): array
    {
        return $this->search('', true);
    }

    /** @return list<CoutRevientChargeConfig> */
    public function findForLotSelection(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.factoryUnit', 'factoryUnit')
            ->addSelect('factoryUnit')
            ->andWhere('c.isActive = true OR factoryUnit.id IS NOT NULL')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.category', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
