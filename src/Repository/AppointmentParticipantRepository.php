<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\AppointmentParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppointmentParticipant> */
class AppointmentParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentParticipant::class);
    }

    public function findFor(Appointment $appointment, User $user): ?AppointmentParticipant
    {
        return $this->findOneBy(['appointment' => $appointment, 'user' => $user]);
    }

    public function hasActiveParticipant(Appointment $appointment, User $user): bool
    {
        return $this->findOneBy(['appointment' => $appointment, 'user' => $user, 'isActive' => true]) instanceof AppointmentParticipant;
    }

    /** @return list<AppointmentParticipant> */
    public function findConflictsFor(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Appointment $exclude = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.appointment', 'a')
            ->addSelect('a')
            ->andWhere('p.user = :user')
            ->andWhere('p.isActive = true')
            ->andWhere('a.isActive = true')
            ->andWhere('a.isDeleted = false')
            ->andWhere('a.status != :cancelled')
            ->andWhere('a.startAt < :endAt')
            ->andWhere('a.endAt > :startAt')
            ->setParameter('user', $user)
            ->setParameter('startAt', $start)
            ->setParameter('endAt', $end)
            ->setParameter('cancelled', 'cancelled');

        if ($exclude instanceof Appointment && $exclude->getId() !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $exclude->getId());
        }

        return $qb->getQuery()->getResult();
    }
}
