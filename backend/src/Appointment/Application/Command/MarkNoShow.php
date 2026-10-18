<?php

namespace App\Appointment\Application\Command;

/**
 * The customer did not come.
 */
final readonly class MarkNoShow
{
    public function __construct(public string $appointmentId)
    {
    }
}
