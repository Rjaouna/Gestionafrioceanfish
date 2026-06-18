<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentParticipantRepository;

final readonly class AppointmentAvailabilityService
{
    public function __construct(private AppointmentParticipantRepository $participantRepository)
    {
    }

    public function assertRange(?\DateTimeImmutable $startAt, ?\DateTimeImmutable $endAt): void
    {
        if (!$startAt instanceof \DateTimeImmutable || !$endAt instanceof \DateTimeImmutable) {
            throw new \DomainException('Sélectionnez une date de début et une date de fin.');
        }

        if ($endAt <= $startAt) {
            throw new \DomainException('La date de fin doit être après la date de début.');
        }
    }

    /** @param list<User> $users */
    public function assertUsersAvailable(array $users, \DateTimeImmutable $startAt, \DateTimeImmutable $endAt, ?Appointment $exclude = null): void
    {
        foreach ($users as $user) {
            $conflicts = $this->participantRepository->findConflictsFor($user, $startAt, $endAt, $exclude);
            if ($conflicts === []) {
                continue;
            }

            $appointment = $conflicts[0]->getAppointment();
            $label = $appointment?->getReference() ? sprintf('%s - %s', $appointment->getReference(), $appointment->getTitle()) : 'un autre rendez-vous';
            throw new \DomainException(sprintf('%s a déjà %s sur ce créneau.', $user->getDisplayName(), $label));
        }
    }

    /** @return list<array{appointment: Appointment, startAt: string, endAt: string}> */
    public function busySlots(User $user, \DateTimeImmutable $startAt, \DateTimeImmutable $endAt, ?Appointment $exclude = null): array
    {
        return array_map(static function ($participant): array {
            $appointment = $participant->getAppointment();

            return [
                'appointment' => $appointment,
                'startAt' => $appointment?->getStartAt()?->format(\DateTimeInterface::ATOM) ?? '',
                'endAt' => $appointment?->getEndAt()?->format(\DateTimeInterface::ATOM) ?? '',
            ];
        }, $this->participantRepository->findConflictsFor($user, $startAt, $endAt, $exclude));
    }
}
