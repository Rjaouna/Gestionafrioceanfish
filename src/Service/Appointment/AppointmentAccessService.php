<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\AppointmentParticipantRepository;
use App\Service\SecurityAccessService;

final readonly class AppointmentAccessService
{
    public const MODULE_SLUG = 'agenda';

    public function __construct(
        private SecurityAccessService $securityAccess,
        private AppointmentParticipantRepository $participantRepository,
    ) {
    }

    public function canAccess(User $user): bool
    {
        return $user->isActive() && $this->securityAccess->canAccessModule($user, self::MODULE_SLUG);
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->securityAccess->isSuperAdmin($user);
    }

    public function isAdmin(User $user): bool
    {
        return $this->securityAccess->isAdmin($user);
    }

    public function canViewAll(User $user): bool
    {
        return $this->canAccess($user) && $this->isAdmin($user);
    }

    public function canCreate(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function canView(User $user, Appointment $appointment): bool
    {
        if (!$this->canAccess($user) || $appointment->isDeleted()) {
            return false;
        }

        if ($this->canViewAll($user)) {
            return true;
        }

        return $this->isCreator($user, $appointment) || $this->isParticipant($user, $appointment);
    }

    public function canEdit(User $user, Appointment $appointment): bool
    {
        if (!$this->canView($user, $appointment) || !$appointment->isActive()) {
            return false;
        }

        return $this->isAdmin($user) || $this->isCreator($user, $appointment);
    }

    public function canDelete(User $user, Appointment $appointment): bool
    {
        return $this->canView($user, $appointment) && $this->isAdmin($user);
    }

    public function canCancel(User $user, Appointment $appointment): bool
    {
        return $this->canEdit($user, $appointment);
    }

    public function canAssignUser(User $user, Appointment $appointment): bool
    {
        return $this->canEdit($user, $appointment) && ($this->isAdmin($user) || $this->isCreator($user, $appointment));
    }

    public function canChangeStatus(User $user, Appointment $appointment): bool
    {
        return $this->canEdit($user, $appointment);
    }

    public function isCreator(User $user, Appointment $appointment): bool
    {
        return $user->getId() !== null && $appointment->getCreatedBy()?->getId() === $user->getId();
    }

    public function isParticipant(User $user, Appointment $appointment): bool
    {
        return $this->participantRepository->hasActiveParticipant($appointment, $user);
    }
}
