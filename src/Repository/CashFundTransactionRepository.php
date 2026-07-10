<?php

namespace App\Repository;

use App\Entity\CashFundTransaction;
use App\Entity\Expense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CashFundTransaction> */
class CashFundTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashFundTransaction::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<CashFundTransaction>
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->buildSearchQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countSearch(array $filters = []): int
    {
        return (int) $this->buildSearchQuery($filters)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function balance(array $filters = []): float
    {
        return (float) $this->buildSearchQuery($filters)
            ->select('COALESCE(SUM(t.amount), 0)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function totalInflow(array $filters = []): float
    {
        return (float) $this->buildSearchQuery($filters)
            ->select('COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function totalOutflow(array $filters = []): float
    {
        return abs((float) $this->buildSearchQuery($filters)
            ->select('COALESCE(SUM(CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END), 0)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function findExpensePayment(Expense $expense): ?CashFundTransaction
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isDeleted = false')
            ->andWhere('t.expense = :expense')
            ->andWhere('t.type = :type')
            ->setParameter('expense', $expense)
            ->setParameter('type', CashFundTransaction::TYPE_EXPENSE_PAYMENT)
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasExpenseReversal(Expense $expense): bool
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isDeleted = false')
            ->andWhere('t.expense = :expense')
            ->andWhere('t.type = :type')
            ->setParameter('expense', $expense)
            ->setParameter('type', CashFundTransaction::TYPE_EXPENSE_REVERSAL)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    private function buildSearchQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('t')
            ->leftJoin('t.expense', 'expense')
            ->leftJoin('t.createdBy', 'creator')
            ->addSelect('expense', 'creator')
            ->andWhere('t.isDeleted = false')
            ->orderBy('t.movementDate', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(t.reference) LIKE :query OR LOWER(COALESCE(t.sourceName, \'\')) LIKE :query OR LOWER(COALESCE(t.notes, \'\')) LIKE :query OR LOWER(COALESCE(expense.reference, \'\')) LIKE :query OR LOWER(COALESCE(expense.title, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['type']) && isset(CashFundTransaction::TYPE_LABELS[(string) $filters['type']])) {
            $builder
                ->andWhere('t.type = :type')
                ->setParameter('type', (string) $filters['type']);
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('t.movementDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('t.movementDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        return $builder;
    }
}
