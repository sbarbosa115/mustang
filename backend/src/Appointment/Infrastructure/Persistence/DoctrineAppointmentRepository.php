<?php

namespace App\Appointment\Infrastructure\Persistence;

use App\Appointment\Domain\Model\Appointment;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Appointment>
 */
final class DoctrineAppointmentRepository extends TenantScopedRepository implements AppointmentRepository
{
    protected function entityClass(): string
    {
        return Appointment::class;
    }

    protected function notFoundCode(): string
    {
        return 'appointment_not_found';
    }

    public function get(string $id): Appointment
    {
        return $this->getScoped($id);
    }

    public function add(Appointment $appointment): void
    {
        $this->persist($appointment);
    }
}
