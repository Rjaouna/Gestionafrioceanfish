<?php

namespace App\Repository;

use App\Entity\Intervenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Intervenant> */
class IntervenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervenant::class);
    }

    /** @return list<Intervenant> */
    public function search(string $query = ''): array
    {
        $builder = $this->createQueryBuilder('i')
            ->andWhere('i.isDeleted = false')
            ->orderBy('i.isActive', 'DESC')
            ->addOrderBy('i.lastname', 'ASC')
            ->addOrderBy('i.firstname', 'ASC');

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(i.firstname) LIKE :query OR LOWER(i.lastname) LIKE :query OR LOWER(COALESCE(i.email, \'\')) LIKE :query OR LOWER(COALESCE(i.phone, \'\')) LIKE :query OR LOWER(i.type) LIKE :query OR LOWER(COALESCE(i.speciality, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<Intervenant> */
    public function findActive(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.isActive = true')
            ->andWhere('i.isDeleted = false')
            ->orderBy('i.lastname', 'ASC')
            ->addOrderBy('i.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
