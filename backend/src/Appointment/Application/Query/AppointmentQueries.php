<?php

namespace App\Appointment\Application\Query;

use App\Appointment\Domain\Model\Appointment;

interface AppointmentQueries
{
    /** The most appointments one list answers with; the calendar asks for a day or a week. */
    public const int LIMIT = 500;

    /**
     * Of the current dealership, by scheduled time.
     *
     * @return list<Appointment>
     */
    public function list(AppointmentFilter $filter): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function byId(string $id): Appointment;
}
