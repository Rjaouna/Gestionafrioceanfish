<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\AppointmentHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppointmentHistory> */
class AppointmentHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentHistory::class);
    }

    /** @return list<AppointmentHistory> */
    public function findFor(Appointment $appointment): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('h.appointment = :appointment')
            ->setParameter('appointment', $appointment)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
