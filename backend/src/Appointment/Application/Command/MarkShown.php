<?php

namespace App\Appointment\Application\Command;

/**
 * The customer came; the deal moves to visit.
 */
final readonly class MarkShown
{
    public function __construct(public string $appointmentId)
    {
    }
}
