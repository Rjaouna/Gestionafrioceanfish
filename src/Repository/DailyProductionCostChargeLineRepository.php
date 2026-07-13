<?php

namespace App\Repository;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\DailyProductionCostChargeLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DailyProductionCostChargeLine> */
class DailyProductionCostChargeLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyProductionCostChargeLine::class);
    }

    public function detachConfigReferences(CoutRevientChargeConfig $config): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->update(DailyProductionCostChargeLine::class, 'line')
            ->set('line.chargeConfig', 'NULL')
            ->andWhere('line.chargeConfig = :config')
            ->setParameter('config', $config)
            ->getQuery()
            ->execute();
    }
}
