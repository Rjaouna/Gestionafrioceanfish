<?php

namespace App\Repository;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\CoutRevientChargeLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CoutRevientChargeLine> */
class CoutRevientChargeLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoutRevientChargeLine::class);
    }

    public function countForConfig(CoutRevientChargeConfig $config): int
    {
        return (int) $this->createQueryBuilder('line')
            ->select('COUNT(line.id)')
            ->andWhere('line.chargeConfig = :config')
            ->setParameter('config', $config)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
