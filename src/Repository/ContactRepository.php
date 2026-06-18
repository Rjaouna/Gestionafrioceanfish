<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Contact> */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * @param array{type?: string|null, city?: string|null} $filters
     *
     * @return list<Contact>
     */
    public function findVisibleFor(User $user, bool $admin, array $filters = []): array
    {
        $builder = $this->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.shares', 's')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('s', 'creator')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.fullName', 'ASC')
            ->addOrderBy('c.type', 'ASC');

        if (!$admin) {
            $builder
                ->andWhere('c.isActive = true')
                ->andWhere('s.user = :user')
                ->andWhere('s.isActive = true')
                ->andWhere('s.canView = true')
                ->setParameter('user', $user);
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $builder
                ->andWhere('c.type = :contactType')
                ->setParameter('contactType', $type);
        }

        $city = trim((string) ($filters['city'] ?? ''));
        if ($city !== '') {
            $builder
                ->andWhere('c.city = :contactCity')
                ->setParameter('contactCity', $city);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<Contact> */
    public function searchVisibleWithMobile(User $user, bool $admin, string $query, int $limit = 2): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $builder = $this->visibleBuilder($user, $admin)
            ->andWhere('(c.mobile IS NOT NULL OR c.mobileSecondary IS NOT NULL OR c.mobileTertiary IS NOT NULL)')
            ->andWhere('LOWER(c.fullName) LIKE :query OR LOWER(COALESCE(c.contactPersonName, \'\')) LIKE :query OR LOWER(COALESCE(c.mobile, \'\')) LIKE :query OR LOWER(COALESCE(c.mobileSecondary, \'\')) LIKE :query OR LOWER(COALESCE(c.mobileTertiary, \'\')) LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('c.fullName', 'ASC')
            ->setMaxResults(max(1, min(2, $limit)));

        return $builder->getQuery()->getResult();
    }

    public function findVisibleOne(User $user, bool $admin, int $id): ?Contact
    {
        return $this->visibleBuilder($user, $admin)
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function visibleBuilder(User $user, bool $admin): \Doctrine\ORM\QueryBuilder
    {
        $builder = $this->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.shares', 's')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.isActive = true');

        if (!$admin) {
            $builder
                ->andWhere('s.user = :user')
                ->andWhere('s.isActive = true')
                ->andWhere('s.canView = true')
                ->setParameter('user', $user);
        }

        return $builder;
    }

    /** @return list<string> */
    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.type AS type')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.type IS NOT NULL')
            ->orderBy('c.type', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) $row['type'],
            $rows,
        )));
    }

    /** @return list<string> */
    public function findDistinctCities(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.city AS city')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.city IS NOT NULL')
            ->andWhere('c.city != :empty')
            ->setParameter('empty', '')
            ->orderBy('c.city', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) $row['city'],
            $rows,
        )));
    }
}
