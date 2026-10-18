<?php

namespace App\Appointment\UI\Http;

use App\Appointment\Domain\Model\Appointment;
use App\Appointment\UI\Http\Output\AppointmentListOutput;
use App\Appointment\UI\Http\Output\AppointmentOutput;
use App\Shared\Application\Port\CustomerDirectory;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds appointment responses, loading customer and rep names for a whole list at once.
 */
final readonly class AppointmentPresenter
{
    public function __construct(private CustomerDirectory $customers, private UserDirectory $users)
    {
    }

    public function one(Appointment $appointment): AppointmentOutput
    {
        return $this->many([$appointment])[0];
    }

    /**
     * @param list<Appointment> $appointments
     */
    public function list(array $appointments): AppointmentListOutput
    {
        return new AppointmentListOutput($this->many($appointments));
    }

    /**
     * @param list<Appointment> $appointments
     *
     * @return list<AppointmentOutput>
     */
    private function many(array $appointments): array
    {
        if ([] === $appointments) {
            return [];
        }
        $customers = $this->customers->names(array_map(static fn (Appointment $a): string => $a->customerId(), $appointments));
        $users = $this->users->names(array_map(static fn (Appointment $a): string => $a->userId(), $appointments));

        return array_map(static fn (Appointment $a): AppointmentOutput => new AppointmentOutput(
            id: $a->id(),
            customer: new CustomerRefOutput($a->customerId(), $customers[$a->customerId()] ?? ''),
            user: new UserRefOutput($a->userId(), $users[$a->userId()] ?? ''),
            scheduledAt: $a->scheduledAt(),
            endsAt: $a->endsAt(),
            durationMinutes: $a->durationMinutes(),
            kind: $a->kind(),
            status: $a->status(),
            dealId: $a->dealId(),
            confirmedAt: $a->confirmedAt(),
            shownAt: $a->shownAt(),
            note: $a->note(),
        ), $appointments);
    }
}
