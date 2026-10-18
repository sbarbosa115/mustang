<?php

namespace App\Appointment\Domain\Model;

/**
 * Where an appointment is.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Shown = 'shown';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
