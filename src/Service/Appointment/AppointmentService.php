<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class AppointmentService
{
    public function __construct(
        private AppointmentRepository $repository,
        private EntityManagerInterface $entityManager,
        private AppointmentAccessService $access,
        private AppointmentAvailabilityService $availability,
        private AppointmentParticipantService $participants,
        private AppointmentHistoryService $history,
        private AppointmentNotificationService $notification,
        private TrashService $trashService,
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function search(User $actor, array $filters, int $page = 1): array
    {
        $this->assertAccess($actor);

        return $this->repository->searchVisible($actor, $this->access->canViewAll($actor), $filters, $page);
    }

    /** @param array<string, mixed> $filters */
    public function events(User $actor, array $filters): array
    {
        $this->assertAccess($actor);
        $appointments = $this->repository->findVisibleBetween(
            $actor,
            $this->access->canViewAll($actor),
            $this->parseDate($filters['start'] ?? null),
            $this->parseDate($filters['end'] ?? null),
            $filters,
        );

        return array_map(fn (Appointment $appointment): array => $this->toCalendarEvent($appointment), $appointments);
    }

    /** @param array<string, mixed> $filters */
    public function upcoming(User $actor, array $filters = [], int $limit = 8): array
    {
        $this->assertAccess($actor);

        return $this->repository->upcoming($actor, $this->access->canViewAll($actor), $filters, $limit);
    }

    /** @param list<User> $participantUsers */
    public function create(Appointment $appointment, User $actor, array $participantUsers = []): Appointment
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($appointment);
        $this->availability->assertRange($appointment->getStartAt(), $appointment->getEndAt());
        if (!in_array($actor, $participantUsers, true)) {
            $participantUsers[] = $actor;
        }
        $this->availability->assertUsersAvailable($participantUsers, $appointment->getStartAt(), $appointment->getEndAt(), $appointment);

        $appointment
            ->setReference($appointment->getReference() ?: $this->nextReference())
            ->setCreatedBy($actor)
            ->setIsActive(true);

        $this->entityManager->persist($appointment);
        $this->participants->syncParticipants($appointment, $participantUsers, $actor, false);
        $this->history->add($appointment, 'Création rendez-vous', $actor, null, $appointment->getReference());
        $this->entityManager->flush();

        return $appointment;
    }

    /** @param list<User> $participantUsers */
    public function update(Appointment $appointment, User $actor, array $participantUsers = []): Appointment
    {
        if (!$this->access->canEdit($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $this->prepare($appointment);
        $this->availability->assertRange($appointment->getStartAt(), $appointment->getEndAt());
        $this->participants->syncParticipants($appointment, $participantUsers, $actor, false);
        $this->history->add($appointment, 'Modification rendez-vous', $actor);
        $this->entityManager->flush();

        return $appointment;
    }

    public function move(Appointment $appointment, User $actor, \DateTimeImmutable $startAt, \DateTimeImmutable $endAt): void
    {
        if (!$this->access->canEdit($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $this->availability->assertRange($startAt, $endAt);
        $users = $this->activeParticipantUsers($appointment);
        $this->availability->assertUsersAvailable($users, $startAt, $endAt, $appointment);

        $old = $this->dateRangeLabel($appointment);
        $appointment->setStartAt($startAt)->setEndAt($endAt);
        $this->history->add($appointment, 'Déplacement rendez-vous', $actor, $old, $this->dateRangeLabel($appointment));
        $this->notification->notifyScheduleChanged($appointment);
        $this->entityManager->flush();
    }

    public function resize(Appointment $appointment, User $actor, \DateTimeImmutable $startAt, \DateTimeImmutable $endAt): void
    {
        if (!$this->access->canEdit($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $this->availability->assertRange($startAt, $endAt);
        $users = $this->activeParticipantUsers($appointment);
        $this->availability->assertUsersAvailable($users, $startAt, $endAt, $appointment);

        $old = $this->dateRangeLabel($appointment);
        $appointment->setStartAt($startAt)->setEndAt($endAt);
        $this->history->add($appointment, 'Redimensionnement rendez-vous', $actor, $old, $this->dateRangeLabel($appointment));
        $this->notification->notifyScheduleChanged($appointment);
        $this->entityManager->flush();
    }

    public function changeStatus(Appointment $appointment, User $actor, string $status, ?string $comment = null): void
    {
        if (!$this->access->canChangeStatus($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        if (!in_array($status, Appointment::STATUS_CHOICES, true)) {
            throw new \DomainException('Statut de rendez-vous invalide.');
        }

        $old = $appointment->getStatus();
        $appointment->setStatus($status);
        $this->history->add($appointment, 'Changement statut', $actor, $old, $status, $comment);
        $this->entityManager->flush();
    }

    public function cancel(Appointment $appointment, User $actor, ?string $reason = null): void
    {
        if (!$this->access->canCancel($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        $old = $appointment->getStatus();
        $appointment
            ->setStatus('cancelled')
            ->setCancellationReason($reason);
        $this->history->add($appointment, 'Annulation rendez-vous', $actor, $old, 'cancelled', $reason);
        $this->notification->notifyCancellation($appointment);
        $this->entityManager->flush();
    }

    public function delete(Appointment $appointment, User $actor): bool
    {
        if (!$this->access->canDelete($actor, $appointment)) {
            throw new AccessDeniedException();
        }

        if (!$this->access->isSuperAdmin($actor)) {
            $this->history->add($appointment, 'Déplacement corbeille', $actor);
            $this->trashService->moveToTrash($appointment, $actor);

            return true;
        }

        $this->entityManager->remove($appointment);
        $this->entityManager->flush();

        return false;
    }

    /** @return array<string, int> */
    public function stats(User $actor, bool $mine = false): array
    {
        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days 23:59:59');
        $monthStart = $today->modify('first day of this month 00:00:00');
        $monthEnd = $today->modify('last day of this month 23:59:59');
        $filters = $mine ? ['mine' => true] : [];

        return [
            'today' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['dateFrom' => $today->format('Y-m-d'), 'dateTo' => $today->format('Y-m-d')]),
            'week' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['dateFrom' => $weekStart->format('Y-m-d'), 'dateTo' => $weekEnd->format('Y-m-d')]),
            'month' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['dateFrom' => $monthStart->format('Y-m-d'), 'dateTo' => $monthEnd->format('Y-m-d')]),
            'pending' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['status' => 'pending']),
            'urgent' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['priority' => 'urgent']),
            'cancelled' => $this->repository->countVisible($actor, $this->access->canViewAll($actor), $filters + ['status' => 'cancelled', 'active' => 'all']),
        ];
    }

    public function toCalendarEvent(Appointment $appointment): array
    {
        return [
            'id' => (string) $appointment->getId(),
            'title' => sprintf('%s%s', $appointment->getReference() ? $appointment->getReference().' - ' : '', $appointment->getTitle()),
            'start' => $appointment->getStartAt()?->format(\DateTimeInterface::ATOM),
            'end' => $appointment->getEndAt()?->format(\DateTimeInterface::ATOM),
            'allDay' => $appointment->isAllDay(),
            'color' => $appointment->getCalendarColor(),
            'extendedProps' => [
                'status' => $appointment->getStatusLabel(),
                'statusCode' => $appointment->getStatus(),
                'priority' => $appointment->getPriorityLabel(),
                'priorityCode' => $appointment->getPriority(),
                'type' => $appointment->getAppointmentTypeLabel(),
                'customerName' => $appointment->getCustomerName(),
                'reference' => $appointment->getReference(),
                'location' => $appointment->getLocation(),
            ],
        ];
    }

    public function nextReference(): string
    {
        $prefix = 'RDV-'.(new \DateTimeImmutable())->format('Y');

        return sprintf('%s-%03d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }

    private function prepare(Appointment $appointment): void
    {
        if (!$appointment->getReference()) {
            $appointment->setReference($this->nextReference());
        }

        if (!in_array($appointment->getStatus(), Appointment::STATUS_CHOICES, true)) {
            $appointment->setStatus('planned');
        }

        if (!in_array($appointment->getPriority(), Appointment::PRIORITY_CHOICES, true)) {
            $appointment->setPriority('normal');
        }

        if (!in_array($appointment->getAppointmentType(), Appointment::TYPE_CHOICES, true)) {
            $appointment->setAppointmentType('client');
        }
    }

    /** @return list<User> */
    private function activeParticipantUsers(Appointment $appointment): array
    {
        $users = [];
        foreach ($appointment->getParticipants() as $participant) {
            if ($participant->isActive() && $participant->getUser() instanceof User) {
                $users[] = $participant->getUser();
            }
        }

        return $users;
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function dateRangeLabel(Appointment $appointment): string
    {
        return sprintf(
            '%s -> %s',
            $appointment->getStartAt()?->format('d/m/Y H:i') ?? '-',
            $appointment->getEndAt()?->format('d/m/Y H:i') ?? '-',
        );
    }
}
