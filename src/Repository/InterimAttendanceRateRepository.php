<?php

namespace App\Repository;

use App\Entity\InterimAttendanceRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimAttendanceRate> */
class InterimAttendanceRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimAttendanceRate::class);
    }

    /** @return list<InterimAttendanceRate> */
    public function ordered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.mode', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
