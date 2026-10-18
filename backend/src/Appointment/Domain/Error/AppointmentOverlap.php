<?php

namespace App\Appointment\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class AppointmentOverlap extends Conflict
{
    public static function with(string $appointmentId): self
    {
        return new self('appointment_overlap', 'The rep already has an appointment at that time.', ['appointment_id' => $appointmentId]);
    }
}
