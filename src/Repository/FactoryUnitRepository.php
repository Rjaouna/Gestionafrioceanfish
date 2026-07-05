<?php

namespace App\Repository;

use App\Entity\FactoryUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FactoryUnit> */
class FactoryUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactoryUnit::class);
    }

    /** @return list<FactoryUnit> */
    public function search(string $query = '', string $type = '', string $status = '', string $saturation = ''): array
    {
        $builder = $this->createQueryBuilder('u')
            ->leftJoin('u.createdBy', 'creator')
            ->leftJoin('u.updatedBy', 'updater')
            ->addSelect('creator', 'updater');

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(u.name) LIKE :query OR LOWER(u.code) LIKE :query OR LOWER(COALESCE(u.locationLabel, \'\')) LIKE :query OR LOWER(COALESCE(u.description, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (isset(FactoryUnit::TYPE_LABELS[$type])) {
            $builder
                ->andWhere('u.type = :type')
                ->setParameter('type', $type);
        }

        if (isset(FactoryUnit::STATUS_LABELS[$status])) {
            $builder
                ->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        if ($saturation === 'sature') {
            $builder->andWhere('u.isSaturated = true');
        } elseif ($saturation === 'non_sature') {
            $builder->andWhere('u.isSaturated = false');
        }

        return $builder
            ->orderBy('u.sortOrder', 'ASC')
            ->addOrderBy('u.type', 'ASC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @param list<string> $types @return list<FactoryUnit> */
    public function usableByTypes(array $types, bool $excludeSaturated = true): array
    {
        $builder = $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->andWhere('u.status = :status')
            ->andWhere('u.type IN (:types)')
            ->setParameter('status', FactoryUnit::STATUS_OPERATIONAL)
            ->setParameter('types', $types);

        if ($excludeSaturated) {
            $builder->andWhere('u.isSaturated = false');
        }

        return $builder
            ->orderBy('u.sortOrder', 'ASC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<FactoryUnit> */
    public function allForChargeSelection(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = true')
            ->orderBy('u.sortOrder', 'ASC')
            ->addOrderBy('u.type', 'ASC')
            ->addOrderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByCodePrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.code LIKE :prefix')
            ->setParameter('prefix', strtoupper($prefix).'%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
