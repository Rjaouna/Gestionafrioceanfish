<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExpenseShare> */
class ExpenseShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseShare::class);
    }

    public function findFor(Expense $expense, User $user): ?ExpenseShare
    {
        return $this->findOneBy(['expense' => $expense, 'user' => $user]);
    }
}
