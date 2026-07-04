<?php

namespace App\Repository;

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
}
