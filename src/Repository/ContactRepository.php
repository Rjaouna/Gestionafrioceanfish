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

    /** @return list<Contact> */
    public function findVisibleFor(User $user, bool $admin): array
    {
        $builder = $this->createQueryBuilder('c')
            ->distinct()
            ->leftJoin('c.shares', 's')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('s', 'creator')
            ->orderBy('c.fullName', 'ASC')
            ->addOrderBy('c.type', 'ASC');

        if (!$admin) {
            $builder
                ->andWhere('c.createdBy = :user OR (c.isActive = true AND s.user = :user AND s.isActive = true AND s.canView = true)')
                ->setParameter('user', $user);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<string> */
    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.type AS type')
            ->andWhere('c.type IS NOT NULL')
            ->orderBy('c.type', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) $row['type'],
            $rows,
        )));
    }
}
