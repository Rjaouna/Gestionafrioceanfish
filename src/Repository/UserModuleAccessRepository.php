<?php

namespace App\Repository;

use App\Entity\AppModule;
use App\Entity\User;
use App\Entity\UserModuleAccess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserModuleAccess> */
class UserModuleAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModuleAccess::class);
    }

    public function hasAccess(User $user, AppModule $module): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'module' => $module]);
    }
}
