<?php

namespace App\Repository;

use App\Entity\Intervention;
use App\Entity\InterventionHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InterventionHistory> */
class InterventionHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterventionHistory::class);
    }

    /** @return list<InterventionHistory> */
    public function findForIntervention(Intervention $intervention): array
    {
        return $this->findBy(['intervention' => $intervention], ['createdAt' => 'DESC']);
    }
}
