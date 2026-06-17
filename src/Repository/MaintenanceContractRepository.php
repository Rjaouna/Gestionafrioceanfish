<?php

namespace App\Repository;

use App\Entity\MaintenanceContract;
use App\Entity\Intervenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceContract> */
class MaintenanceContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceContract::class);
    }

    /** @return list<MaintenanceContract> */
    public function search(string $query = '', ?string $status = null): array
    {
        $builder = $this->createQueryBuilder('c')
            ->leftJoin('c.intervenant', 'i')
            ->addSelect('i')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.isActive', 'DESC')
            ->addOrderBy('c.renewalDate', 'ASC')
            ->addOrderBy('c.customerName', 'ASC');

        $query = mb_strtolower(trim($query));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(c.reference) LIKE :query OR LOWER(c.customerName) LIKE :query OR LOWER(COALESCE(c.customerEmail, \'\')) LIKE :query OR LOWER(COALESCE(c.customerPhone, \'\')) LIKE :query OR LOWER(COALESCE(c.contractType, \'\')) LIKE :query OR LOWER(COALESCE(i.firstname, \'\')) LIKE :query OR LOWER(COALESCE(i.lastname, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if ($status !== null && $status !== '') {
            $builder
                ->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<MaintenanceContract> */
    public function findActiveContracts(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->andWhere('c.isDeleted = false')
            ->orderBy('c.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<MaintenanceContract> */
    public function findActiveForIntervenant(Intervenant $intervenant): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.intervenant = :intervenant')
            ->setParameter('intervenant', $intervenant)
            ->orderBy('c.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function findDistinctContractTypes(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.contractType AS contractType')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.contractType IS NOT NULL')
            ->andWhere('c.contractType <> :empty')
            ->setParameter('empty', '')
            ->orderBy('c.contractType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) $row['contractType'],
            $rows,
        )));
    }

    /** @return list<MaintenanceContract> */
    public function findExpiringSoon(int $days = 10, int $limit = 6): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.intervenant', 'i')
            ->addSelect('i')
            ->andWhere('c.isActive = true')
            ->andWhere('c.isDeleted = false')
            ->andWhere('c.endDate IS NOT NULL')
            ->andWhere('c.endDate BETWEEN :today AND :limitDate')
            ->setParameter('today', $today)
            ->setParameter('limitDate', $today->modify(sprintf('+%d days', $days)))
            ->orderBy('c.endDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
