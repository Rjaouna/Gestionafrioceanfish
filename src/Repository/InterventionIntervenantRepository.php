<?php

namespace App\Repository;

use App\Entity\Intervenant;
use App\Entity\Intervention;
use App\Entity\InterventionIntervenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterventionIntervenant> */
class InterventionIntervenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterventionIntervenant::class);
    }

    public function findFor(Intervention $intervention, Intervenant $intervenant): ?InterventionIntervenant
    {
        return $this->findOneBy(['intervention' => $intervention, 'intervenant' => $intervenant]);
    }
}
