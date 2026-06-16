<?php

namespace App\Repository;

use App\Entity\AppModule;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppModule> */
class AppModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppModule::class);
    }

    /** @return list<AppModule> */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.userAccesses', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('m.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
