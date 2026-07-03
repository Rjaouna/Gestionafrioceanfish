<?php

namespace App\Repository;

use App\Entity\InterimWorkerAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterimWorkerAction> */
class InterimWorkerActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterimWorkerAction::class);
    }
}
