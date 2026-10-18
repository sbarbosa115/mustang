<?php

namespace App\Appointment\Domain\Repository;

use App\Appointment\Domain\Model\Appointment;

interface AppointmentRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Appointment;

    /**
     * For the worker, where nobody is signed in: the dealership comes from the message.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getInDealership(string $id, string $dealershipId): Appointment;

    public function add(Appointment $appointment): void;

    /**
     * The rep's appointments that take their time between $from and $to (cancelled and no-show ones do not).
     *
     * @return list<Appointment>
     */
    public function overlapping(string $dealershipId, string $userId, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $exceptId = null): array;
}
