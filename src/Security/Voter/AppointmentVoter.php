<?php

namespace App\Security\Voter;

use App\Entity\Appointment;
use App\Entity\User;
use App\Service\Appointment\AppointmentAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class AppointmentVoter extends Voter
{
    public const VIEW = 'APPOINTMENT_VIEW';
    public const CREATE = 'APPOINTMENT_CREATE';
    public const EDIT = 'APPOINTMENT_EDIT';
    public const DELETE = 'APPOINTMENT_DELETE';
    public const CANCEL = 'APPOINTMENT_CANCEL';
    public const ASSIGN_USER = 'APPOINTMENT_ASSIGN_USER';
    public const CHANGE_STATUS = 'APPOINTMENT_CHANGE_STATUS';
    public const VIEW_ALL = 'APPOINTMENT_VIEW_ALL';

    public function __construct(private readonly AppointmentAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::CANCEL,
            self::ASSIGN_USER,
            self::CHANGE_STATUS,
            self::VIEW_ALL,
        ], true) && ($subject === null || $subject instanceof Appointment);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->access->canCreate($user),
            self::VIEW_ALL => $this->access->canViewAll($user),
            self::VIEW => $subject instanceof Appointment && $this->access->canView($user, $subject),
            self::EDIT => $subject instanceof Appointment && $this->access->canEdit($user, $subject),
            self::DELETE => $subject instanceof Appointment && $this->access->canDelete($user, $subject),
            self::CANCEL => $subject instanceof Appointment && $this->access->canCancel($user, $subject),
            self::ASSIGN_USER => $subject instanceof Appointment && $this->access->canAssignUser($user, $subject),
            self::CHANGE_STATUS => $subject instanceof Appointment && $this->access->canChangeStatus($user, $subject),
            default => false,
        };
    }
}
