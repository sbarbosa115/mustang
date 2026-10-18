<?php

namespace App\Appointment\Domain\Error;

use App\Appointment\Domain\Model\AppointmentStatus;
use App\Shared\Domain\Error\Conflict;

final class AppointmentNotOpen extends Conflict
{
    public static function in(AppointmentStatus $status): self
    {
        return new self('appointment_not_open', 'This appointment is already '.$status->value.'.', ['status' => $status->value]);
    }
}
