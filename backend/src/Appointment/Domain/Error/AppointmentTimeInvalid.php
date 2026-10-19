<?php

namespace App\Appointment\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

final class AppointmentTimeInvalid extends InvalidValue
{
    public static function inThePast(): self
    {
        return new self('appointment_in_past', 'An appointment cannot be booked in the past.');
    }

    public static function unreadable(string $value): self
    {
        return new self('invalid_scheduled_at', 'The time must look like 2026-10-20T15:30, in the dealership\'s timezone.', ['value' => $value]);
    }
}
