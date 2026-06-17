<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\AppointmentParticipant;

final class AppointmentNotificationService
{
    public function notifyParticipantAdded(AppointmentParticipant $participant): void
    {
        $participant->setNotifiedAt(new \DateTimeImmutable());
    }

    public function notifyScheduleChanged(Appointment $appointment): void
    {
    }

    public function notifyCancellation(Appointment $appointment): void
    {
    }
}
