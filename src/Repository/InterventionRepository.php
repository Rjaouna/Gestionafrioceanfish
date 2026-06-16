<?php

namespace App\Repository;

use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Intervention> */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    /** @return list<Intervention> */
    public function search(string $query = '', ?string $status = null): array
    {
        $builder = $this->createQueryBuilder('i')
            ->leftJoin('i.contract', 'c')
            ->leftJoin('i.intervenant', 'primaryIntervenant')
            ->leftJoin('i.assignments', 'a')
            ->leftJoin('a.intervenant', 'intervenant')
            ->addSelect('c', 'primaryIntervenant', 'a', 'intervenant')
            ->orderBy('i.isActive', 'DESC')
            ->addOrderBy('i.plannedAt', 'ASC')
            ->addOrderBy('i.createdAt', 'DESC');

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(i.title) LIKE :query OR LOWER(i.reference) LIKE :query OR LOWER(i.customerName) LIKE :query OR LOWER(COALESCE(i.customerEmail, \'\')) LIKE :query OR LOWER(COALESCE(i.customerPhone, \'\')) LIKE :query OR LOWER(COALESCE(c.reference, \'\')) LIKE :query OR LOWER(COALESCE(primaryIntervenant.firstname, \'\')) LIKE :query OR LOWER(COALESCE(primaryIntervenant.lastname, \'\')) LIKE :query OR LOWER(COALESCE(intervenant.firstname, \'\')) LIKE :query OR LOWER(COALESCE(intervenant.lastname, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if ($status !== null && $status !== '') {
            $builder
                ->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<Intervention> */
    public function findUpcoming(int $limit = 6): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.contract', 'c')
            ->leftJoin('i.intervenant', 'intervenant')
            ->addSelect('c', 'intervenant')
            ->andWhere('i.isActive = true')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.plannedAt IS NOT NULL')
            ->andWhere('i.plannedAt >= :now')
            ->setParameter('statuses', ['a_planifier', 'planifiee'])
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.plannedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Intervention> */
    public function findOpen(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.contract', 'c')
            ->leftJoin('i.intervenant', 'intervenant')
            ->addSelect('c', 'intervenant')
            ->addSelect("CASE WHEN i.status = 'en_cours' THEN 0 WHEN i.status = 'planifiee' THEN 1 ELSE 2 END AS HIDDEN statusRank")
            ->addSelect('CASE WHEN i.plannedAt IS NULL THEN 1 ELSE 0 END AS HIDDEN plannedMissing')
            ->andWhere('i.isActive = true')
            ->andWhere('i.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', ['terminee', 'annulee'])
            ->orderBy('statusRank', 'ASC')
            ->addOrderBy('plannedMissing', 'ASC')
            ->addOrderBy('i.plannedAt', 'ASC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
