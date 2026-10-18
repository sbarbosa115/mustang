<?php

namespace App\Appointment\Domain\Model;

/**
 * What the appointment is for.
 */
enum AppointmentKind: string
{
    case Visit = 'visit';
    case TestDrive = 'test_drive';
    case Delivery = 'delivery';
}
