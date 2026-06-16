<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExpenseDocument> */
class ExpenseDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseDocument::class);
    }

    public function findPrimaryForExpense(Expense $expense): ?ExpenseDocument
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.expense = :expense')
            ->andWhere('d.isActive = true')
            ->setParameter('expense', $expense)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
