<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Appointment> */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /** @param array<string, mixed> $filters */
    public function searchVisible(User $actor, bool $viewAll, array $filters = [], int $page = 1, int $limit = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, min(60, $limit));
        $qb = $this->visibleQuery($actor, $viewAll, $filters)
            ->orderBy('a.startAt', 'ASC')
            ->addOrderBy('a.id', 'DESC');

        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('orderBy')
            ->select('COUNT(DISTINCT a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->select('DISTINCT a')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function findVisibleBetween(User $actor, bool $viewAll, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, array $filters = []): array
    {
        $qb = $this->visibleQuery($actor, $viewAll, $filters);

        if ($start instanceof \DateTimeImmutable) {
            $qb->andWhere('a.endAt >= :calendarStart')->setParameter('calendarStart', $start);
        }

        if ($end instanceof \DateTimeImmutable) {
            $qb->andWhere('a.startAt <= :calendarEnd')->setParameter('calendarEnd', $end);
        }

        return $qb
            ->select('DISTINCT a')
            ->orderBy('a.startAt', 'ASC')
            ->setMaxResults(700)
            ->getQuery()
            ->getResult();
    }

    public function nextReferenceNumber(string $prefix): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult() + 1;
    }

    /** @param array<string, mixed> $filters */
    public function countVisible(User $actor, bool $viewAll, array $filters = []): int
    {
        return (int) $this->visibleQuery($actor, $viewAll, $filters)
            ->select('COUNT(DISTINCT a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function upcoming(User $actor, bool $viewAll, array $filters = [], int $limit = 8): array
    {
        $filters['dateFrom'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this->visibleQuery($actor, $viewAll, $filters)
            ->select('DISTINCT a')
            ->orderBy('a.startAt', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function visibleQuery(User $actor, bool $viewAll, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.participants', 'participants')
            ->addSelect('participants')
            ->leftJoin('participants.user', 'participantUser')
            ->addSelect('participantUser')
            ->leftJoin('a.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('a.isDeleted = false');

        $this->applyVisibility($qb, $actor, $viewAll && empty($filters['mine']));
        $this->applyFilters($qb, $filters);

        return $qb;
    }

    private function applyVisibility(QueryBuilder $qb, User $actor, bool $viewAll): void
    {
        if ($viewAll) {
            return;
        }

        $qb
            ->andWhere('(a.createdBy = :actor OR (participants.user = :actor AND participants.isActive = true))')
            ->setParameter('actor', $actor);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(a.title) LIKE :query OR LOWER(a.reference) LIKE :query OR LOWER(a.customerName) LIKE :query OR LOWER(a.location) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        foreach (['status', 'priority', 'appointmentType'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $qb->andWhere(sprintf('a.%s = :%s', $field, $field))->setParameter($field, $value);
            }
        }

        $dateFrom = $this->dateFrom($filters['dateFrom'] ?? null, false);
        if ($dateFrom instanceof \DateTimeImmutable) {
            $qb->andWhere('a.endAt >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }

        $dateTo = $this->dateFrom($filters['dateTo'] ?? null, true);
        if ($dateTo instanceof \DateTimeImmutable) {
            $qb->andWhere('a.startAt <= :dateTo')->setParameter('dateTo', $dateTo);
        }

        $participantId = (int) ($filters['participant'] ?? 0);
        if ($participantId > 0) {
            $qb
                ->andWhere('participantUser.id = :participantId')
                ->andWhere('participants.isActive = true')
                ->setParameter('participantId', $participantId);
        }

        $createdById = (int) ($filters['createdBy'] ?? 0);
        if ($createdById > 0) {
            $qb->andWhere('creator.id = :createdById')->setParameter('createdById', $createdById);
        }

        $active = (string) ($filters['active'] ?? 'active');
        if ($active === 'archived') {
            $qb->andWhere('a.isActive = false');
        } elseif ($active !== 'all') {
            $qb->andWhere('a.isActive = true');
        }
    }

    private function dateFrom(mixed $value, bool $endOfDay): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return new \DateTimeImmutable($value.($endOfDay ? ' 23:59:59' : ' 00:00:00'));
            }

            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
