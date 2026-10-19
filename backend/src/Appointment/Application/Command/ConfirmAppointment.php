<?php

namespace App\Appointment\Application\Command;

/**
 * The rep records that the customer confirmed.
 */
final readonly class ConfirmAppointment
{
    public function __construct(public string $appointmentId)
    {
    }
}
