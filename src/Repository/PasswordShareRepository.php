<?php

namespace App\Repository;

use App\Entity\PasswordEntry;
use App\Entity\PasswordShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordShare> */
class PasswordShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordShare::class);
    }

    public function findFor(PasswordEntry $entry, User $user): ?PasswordShare
    {
        return $this->findOneBy(['passwordEntry' => $entry, 'user' => $user]);
    }
}
