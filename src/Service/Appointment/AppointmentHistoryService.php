<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\AppointmentHistory;
use App\Entity\User;
use App\Repository\AppointmentHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AppointmentHistoryService
{
    public function __construct(
        private AppointmentHistoryRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function add(Appointment $appointment, string $action, User $actor, ?string $oldValue = null, ?string $newValue = null, ?string $comment = null): AppointmentHistory
    {
        $history = (new AppointmentHistory())
            ->setAppointment($appointment)
            ->setAction($action)
            ->setOldValue($oldValue)
            ->setNewValue($newValue)
            ->setComment($comment)
            ->setCreatedBy($actor);

        $this->entityManager->persist($history);

        return $history;
    }

    /** @return list<AppointmentHistory> */
    public function getHistory(Appointment $appointment): array
    {
        return $this->repository->findFor($appointment);
    }
}
