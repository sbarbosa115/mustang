<?php

namespace App\Appointment\Application\Command;

final readonly class CancelAppointment
{
    public function __construct(public string $appointmentId)
    {
    }
}
