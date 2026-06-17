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
    /**
     * @param array{category?: string, issuer?: string, language?: string, status?: string} $filters
     *
     * @return list<Document>
     */
    public function searchAccessible(User $user, bool $admin, string $query = '', int $page = 1, int $perPage = 12, array $filters = []): array
    {
        return $this->buildAccessibleQuery($user, $admin, $query, $filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array{category?: string, issuer?: string, language?: string, status?: string} $filters */
    public function countAccessible(User $user, bool $admin, string $query = '', array $filters = []): int
    {
        return (int) $this->buildAccessibleQuery($user, $admin, $query, $filters)
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

    /** @return list<string> */
    public function findInternalReferencesByPrefix(string $prefix): array
    {
        return array_column($this->createQueryBuilder('d')
            ->select('d.internalReference')
            ->andWhere('d.internalReference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getArrayResult(), 'internalReference');
    }

 /** @return list<string> */
public function distinctAccessibleValues(User $user, bool $admin, string $field): array
{
    if (!in_array($field, ['category', 'issuer', 'language'], true)) {
        throw new \InvalidArgumentException('Champ de filtre document invalide.');
    }

    $rows = $this->buildAccessibleQuery($user, $admin, '')
        ->select(sprintf('d.%s AS value', $field))
        ->andWhere(sprintf('d.%s IS NOT NULL', $field))
        ->andWhere(sprintf("d.%s <> ''", $field))
        ->resetDQLPart('orderBy')
        ->addOrderBy(sprintf('d.%s', $field), 'ASC')
        ->getQuery()
        ->getArrayResult();

    return array_values(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        array_column($rows, 'value'),
    )));
}

    /** @param array{category?: string, issuer?: string, language?: string, status?: string} $filters */
    private function buildAccessibleQuery(User $user, bool $admin, string $query, array $filters = []): QueryBuilder
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
                ->andWhere('d.isActive = true')
                ->andWhere('s.user = :user')
                ->andWhere('s.isActive = true')
                ->andWhere('s.canView = true')
                ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
                ->setParameter('user', $user)
                ->setParameter('now', new \DateTimeImmutable());
        }

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere(
                    'LOWER(d.name) LIKE :query
                    OR LOWER(COALESCE(d.description, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.category, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.internalReference, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.issuer, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.language, \'\')) LIKE :query
                    OR LOWER(d.status) LIKE :query
                    OR LOWER(COALESCE(d.tags, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.confidentialityLevel, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.version, \'\')) LIKE :query
                    OR LOWER(COALESCE(d.originalFileName, \'\')) LIKE :query
                    OR LOWER(d.mimeType) LIKE :query
                    OR LOWER(COALESCE(creator.email, \'\')) LIKE :query
                    OR LOWER(COALESCE(creator.firstName, \'\')) LIKE :query
                    OR LOWER(COALESCE(creator.lastName, \'\')) LIKE :query
                    OR (:queryActive = true AND d.isActive = true)
                    OR (:queryArchived = true AND d.isActive = false)'
                )
                ->setParameter('query', '%'.$query.'%')
                ->setParameter('queryActive', in_array($query, ['actif', 'active', 'disponible'], true))
                ->setParameter('queryArchived', in_array($query, ['archive', 'archivé', 'inactif', 'desactive', 'désactivé'], true));
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $builder
                ->andWhere('d.category = :filterCategory')
                ->setParameter('filterCategory', $category);
        }

        $issuer = trim((string) ($filters['issuer'] ?? ''));
        if ($issuer !== '') {
            $builder
                ->andWhere('d.issuer = :filterIssuer')
                ->setParameter('filterIssuer', $issuer);
        }

        $language = trim((string) ($filters['language'] ?? ''));
        if ($language !== '') {
            $builder
                ->andWhere('d.language = :filterLanguage')
                ->setParameter('filterLanguage', $language);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $builder
                ->andWhere('d.status = :filterStatus')
                ->setParameter('filterStatus', $status);
        }

        return $builder;
    }
}
