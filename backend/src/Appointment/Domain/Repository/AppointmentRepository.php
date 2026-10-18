<?php

namespace App\Appointment\Domain\Repository;

use App\Appointment\Domain\Model\Appointment;

interface AppointmentRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Appointment;

    public function add(Appointment $appointment): void;
}
