<?php

namespace App\Repository;

use App\Entity\PasswordEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordEntry> */
class PasswordEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordEntry::class);
    }

    /** @return list<PasswordEntry> */
    public function findVisibleFor(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.shares', 's')
            ->leftJoin('s.user', 'su')
            ->leftJoin('p.createdBy', 'cb')
            ->addSelect('s')
            ->addSelect('su')
            ->addSelect('cb')
            ->andWhere('(s.user = :user AND s.canView = true AND p.isValidated = true AND p.isActive = true) OR p.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PasswordEntry> */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.shares', 's')
            ->leftJoin('s.user', 'su')
            ->leftJoin('p.createdBy', 'cb')
            ->addSelect('s')
            ->addSelect('su')
            ->addSelect('cb')
            ->orderBy('p.isValidated', 'ASC')
            ->addOrderBy('p.isActive', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PasswordEntry> */
    public function findSendableFor(User $user, bool $includeAllEntries = false): array
    {
        $builder = $this->createQueryBuilder('p')
            ->distinct()
            ->leftJoin('p.shares', 's')
            ->leftJoin('s.user', 'su')
            ->leftJoin('p.createdBy', 'cb')
            ->addSelect('s')
            ->addSelect('su')
            ->addSelect('cb')
            ->andWhere('p.isValidated = true')
            ->andWhere('p.isActive = true')
            ->orderBy('p.name', 'ASC');

        if (!$includeAllEntries) {
            $builder
                ->andWhere('(s.user = :user AND s.canView = true) OR p.createdBy = :user')
                ->setParameter('user', $user);
        }

        return $builder->getQuery()->getResult();
    }

    public function countPendingValidation(?User $user = null): int
    {
        $builder = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isValidated = false')
            ->andWhere('p.isActive = true');

        if ($user instanceof User) {
            $builder
                ->andWhere('p.createdBy = :user')
                ->setParameter('user', $user);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }
}
