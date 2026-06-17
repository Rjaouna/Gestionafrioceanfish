<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Expense> */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<Expense>
     */
    public function searchVisible(User $user, bool $admin, array $filters = [], int $page = 1, int $perPage = 12): array
    {
        return $this->buildVisibleQuery($user, $admin, $filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countVisible(User $user, bool $admin, array $filters = []): int
    {
        return (int) $this->buildVisibleQuery($user, $admin, $filters)
            ->select('COUNT(DISTINCT e.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(User $user, bool $admin, string $status): int
    {
        return $this->countVisible($user, $admin, ['status' => $status, 'active' => 'active']);
    }

    public function sumByStatus(User $user, bool $admin, ?string $status = null, bool $monthOnly = false): float
    {
        $filters = ['active' => 'active'];
        if ($status !== null) {
            $filters['status'] = $status;
        }

        $builder = $this->buildVisibleQuery($user, $admin, $filters)
            ->select('COALESCE(SUM(e.amountTtc), 0)')
            ->resetDQLPart('orderBy');

        if ($status === null) {
            $builder
                ->andWhere('e.status != :cancelledStatus')
                ->setParameter('cancelledStatus', Expense::STATUS_CANCELLED);
        }

        if ($monthOnly) {
            $start = new \DateTimeImmutable('first day of this month 00:00:00');
            $end = $start->modify('first day of next month');
            $builder
                ->andWhere('e.expenseDate >= :monthStart')
                ->andWhere('e.expenseDate < :monthEnd')
                ->setParameter('monthStart', $start)
                ->setParameter('monthEnd', $end);
        }

        return (float) $builder->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<array{label: string, total: string}>
     */
    public function totalsByCategory(User $user, bool $admin): array
    {
        $rows = $this->buildVisibleQuery($user, $admin, ['active' => 'active'])
            ->select('COALESCE(c.name, :uncategorized) AS label, COALESCE(SUM(e.amountTtc), 0) AS total')
            ->setParameter('uncategorized', 'Sans catégorie')
            ->andWhere('e.status != :cancelledStatus')
            ->setParameter('cancelledStatus', Expense::STATUS_CANCELLED)
            ->groupBy('c.id')
            ->addGroupBy('c.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'label' => (string) $row['label'],
            'total' => (string) $row['total'],
        ], $rows);
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    private function buildVisibleQuery(User $user, bool $admin, array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('e')
            ->distinct()
            ->leftJoin('e.category', 'c')
            ->leftJoin('e.createdBy', 'creator')
            ->leftJoin('e.documents', 'documents')
            ->leftJoin('e.shares', 'shares')
            ->addSelect('c', 'creator', 'documents', 'shares')
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC');

        if (!$admin) {
            $builder
                ->andWhere('e.isActive = true')
                ->andWhere('shares.user = :visibleUser')
                ->andWhere('shares.isActive = true')
                ->andWhere('shares.canView = true')
                ->setParameter('visibleUser', $user);
        }

        $active = (string) ($filters['active'] ?? 'active');
        if ($active === 'active') {
            $builder->andWhere('e.isActive = true');
        } elseif ($active === 'archived') {
            $builder->andWhere('e.isActive = false');
        }

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(e.title) LIKE :query OR LOWER(e.reference) LIKE :query OR LOWER(e.supplierName) LIKE :query OR LOWER(COALESCE(e.invoiceNumber, \'\')) LIKE :query OR LOWER(COALESCE(c.name, \'\')) LIKE :query OR LOWER(COALESCE(creator.email, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['category'])) {
            $builder
                ->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', (int) $filters['category']);
        }

        if (!empty($filters['status']) && isset(Expense::STATUS_LABELS[(string) $filters['status']])) {
            $builder
                ->andWhere('e.status = :status')
                ->setParameter('status', (string) $filters['status']);
        }

        if (!empty($filters['paymentMethod'])) {
            $builder
                ->andWhere('e.paymentMethod = :paymentMethod')
                ->setParameter('paymentMethod', (string) $filters['paymentMethod']);
        }

        if (!empty($filters['creator'])) {
            $builder
                ->andWhere('creator.id = :creatorId')
                ->setParameter('creatorId', (int) $filters['creator']);
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('e.expenseDate >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('e.expenseDate <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        if ((string) ($filters['minAmount'] ?? '') !== '') {
            $builder
                ->andWhere('e.amountTtc >= :minAmount')
                ->setParameter('minAmount', str_replace(',', '.', (string) $filters['minAmount']));
        }

        if ((string) ($filters['maxAmount'] ?? '') !== '') {
            $builder
                ->andWhere('e.amountTtc <= :maxAmount')
                ->setParameter('maxAmount', str_replace(',', '.', (string) $filters['maxAmount']));
        }

        return $builder;
    }
}
