<?php

namespace App\Repository;

use App\Entity\ExpenseCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExpenseCategory> */
class ExpenseCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpenseCategory::class);
    }

    /** @return list<ExpenseCategory> */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ExpenseCategory> */
    public function search(string $query = '', bool $includeArchived = true): array
    {
        $builder = $this->createQueryBuilder('c')
            ->orderBy('c.isActive', 'DESC')
            ->addOrderBy('c.name', 'ASC');

        if (!$includeArchived) {
            $builder->andWhere('c.isActive = true');
        }

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(c.name) LIKE :query OR LOWER(COALESCE(c.description, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return $builder->getQuery()->getResult();
    }
}
