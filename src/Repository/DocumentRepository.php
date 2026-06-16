<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Document> */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /** @return list<Document> */
    public function searchAccessible(User $user, bool $admin, string $query = '', int $page = 1, int $perPage = 12): array
    {
        return $this->buildAccessibleQuery($user, $admin, $query)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countAccessible(User $user, bool $admin, string $query = ''): int
    {
        return (int) $this->buildAccessibleQuery($user, $admin, $query)
            ->select('COUNT(DISTINCT d.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAccessibleByState(User $user, bool $admin, bool $active): int
    {
        return (int) $this->buildAccessibleQuery($user, $admin, '')
            ->select('COUNT(DISTINCT d.id)')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', $active)
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAccessibleShared(User $user, bool $admin): int
    {
        return (int) $this->buildAccessibleQuery($user, $admin, '')
            ->select('COUNT(DISTINCT d.id)')
            ->andWhere('s.isActive = true')
            ->andWhere('s.canView = true')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :shareNow')
            ->setParameter('shareNow', new \DateTimeImmutable())
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function buildAccessibleQuery(User $user, bool $admin, string $query): QueryBuilder
    {
        $builder = $this->createQueryBuilder('d')
            ->distinct()
            ->leftJoin('d.shares', 's')
            ->leftJoin('d.createdBy', 'creator')
            ->addSelect('s', 'creator')
            ->orderBy('d.isActive', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');

        if (!$admin) {
            $builder
                ->andWhere('d.createdBy = :user OR (d.isActive = true AND s.user = :user AND s.isActive = true AND s.canView = true AND (s.expiresAt IS NULL OR s.expiresAt > :now))')
                ->setParameter('user', $user)
                ->setParameter('now', new \DateTimeImmutable());
        }

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(d.name) LIKE :query OR LOWER(COALESCE(d.description, \'\')) LIKE :query OR LOWER(d.mimeType) LIKE :query OR LOWER(COALESCE(creator.email, \'\')) LIKE :query OR LOWER(COALESCE(creator.firstName, \'\')) LIKE :query OR LOWER(COALESCE(creator.lastName, \'\')) LIKE :query OR (:queryActive = true AND d.isActive = true) OR (:queryArchived = true AND d.isActive = false)')
                ->setParameter('query', '%'.$query.'%')
                ->setParameter('queryActive', in_array($query, ['actif', 'active', 'disponible'], true))
                ->setParameter('queryArchived', in_array($query, ['archive', 'archivé', 'inactif', 'desactive', 'désactivé'], true));
        }

        return $builder;
    }
}
