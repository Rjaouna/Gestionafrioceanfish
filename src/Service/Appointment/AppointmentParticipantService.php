<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\AppointmentParticipant;
use App\Entity\User;
use App\Repository\AppointmentParticipantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class AppointmentParticipantService
{
    public function __construct(
        private AppointmentParticipantRepository $repository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private AppointmentAccessService $access,
        private AppointmentAvailabilityService $availability,
        private AppointmentHistoryService $history,
        private AppointmentNotificationService $notification,
    ) {
    }

    /** @param list<User> $users */
    public function syncParticipants(Appointment $appointment, array $users, User $actor, bool $flush = false): void
    {
        if ($appointment->getId() !== null && !$this->access->canAssignUser($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $startAt = $appointment->getStartAt();
        $endAt = $appointment->getEndAt();
        $this->availability->assertRange($startAt, $endAt);
        \assert($startAt instanceof \DateTimeImmutable && $endAt instanceof \DateTimeImmutable);

        $usersById = [];
        foreach ($users as $user) {
            if ($user->getId() !== null && $user->isActive()) {
                $usersById[$user->getId()] = $user;
            }
        }

        if ($usersById === [] && $actor->getId() !== null) {
            $usersById[$actor->getId()] = $actor;
        }

        $this->availability->assertUsersAvailable(array_values($usersById), $startAt, $endAt, $appointment);

        foreach ($appointment->getParticipants()->toArray() as $participant) {
            $userId = $participant->getUser()?->getId();
            if ($userId !== null && isset($usersById[$userId])) {
                $participant->setIsActive(true);
                unset($usersById[$userId]);
                continue;
            }

            $appointment->removeParticipant($participant);
            $this->entityManager->remove($participant);
            $this->history->add($appointment, 'Retrait participant', $actor, $participant->getUser()?->getDisplayName(), null);
        }

        foreach ($usersById as $user) {
            $this->addParticipant($appointment, $user, $actor, $user->getId() === $actor->getId() ? 'organizer' : 'participant', 'invited', true, false);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function addParticipant(Appointment $appointment, User $user, User $actor, string $role = 'participant', string $responseStatus = 'invited', bool $required = true, bool $flush = true): AppointmentParticipant
    {
        if ($appointment->getId() !== null && !$this->access->canAssignUser($actor, $appointment) && $user->getId() !== $actor->getId()) {
            throw new AccessDeniedException();
        }

        $startAt = $appointment->getStartAt();
        $endAt = $appointment->getEndAt();
        $this->availability->assertRange($startAt, $endAt);
        \assert($startAt instanceof \DateTimeImmutable && $endAt instanceof \DateTimeImmutable);
        $this->availability->assertUsersAvailable([$user], $startAt, $endAt, $appointment);

        $participant = ($appointment->getId() !== null ? $this->repository->findFor($appointment, $user) : null) ?? (new AppointmentParticipant())
            ->setAppointment($appointment)
            ->setUser($user)
            ->setCreatedBy($actor);
        $participant
            ->setRoleInAppointment(in_array($role, AppointmentParticipant::ROLE_CHOICES, true) ? $role : 'participant')
            ->setResponseStatus(in_array($responseStatus, AppointmentParticipant::RESPONSE_CHOICES, true) ? $responseStatus : 'invited')
            ->setIsRequired($required)
            ->setIsActive(true);

        $appointment->addParticipant($participant);
        $this->notification->notifyParticipantAdded($participant);
        $this->entityManager->persist($participant);
        $this->history->add($appointment, 'Ajout participant', $actor, null, $user->getDisplayName(), $participant->getRoleLabel());

        if ($flush) {
            $this->entityManager->flush();
        }

        return $participant;
    }

    public function removeParticipant(Appointment $appointment, int $participantId, User $actor): void
    {
        if (!$this->access->canAssignUser($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $participant = $this->repository->find($participantId);
        if (!$participant instanceof AppointmentParticipant || $participant->getAppointment()?->getId() !== $appointment->getId()) {
            throw new \DomainException('Participant introuvable.');
        }

        $name = $participant->getUser()?->getDisplayName();
        $appointment->removeParticipant($participant);
        $this->entityManager->remove($participant);
        $this->history->add($appointment, 'Retrait participant', $actor, $name, null);
        $this->entityManager->flush();
    }

    public function respond(AppointmentParticipant $participant, User $actor, string $response): void
    {
        if ($participant->getUser()?->getId() !== $actor->getId()) {
            throw new AccessDeniedException();
        }

        if (!in_array($response, ['accepted', 'declined', 'pending'], true)) {
            throw new \DomainException('Réponse de participation invalide.');
        }

        $old = $participant->getResponseStatus();
        $participant->setResponseStatus($response);
        if ($participant->getAppointment() instanceof Appointment) {
            $this->history->add($participant->getAppointment(), 'Réponse participant', $actor, $old, $response);
        }
        $this->entityManager->flush();
    }

    /** @return list<User> */
    public function usersFromIds(array $ids): array
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($cleanIds === []) {
            return [];
        }

        return $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->andWhere('u.isActive = true')
            ->setParameter('ids', $cleanIds)
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
