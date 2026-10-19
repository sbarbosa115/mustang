<?php

namespace App\Appointment\Application;

use App\Appointment\Application\Port\AppointmentPeople;
use App\Appointment\Domain\Error\AppointmentOverlap;
use App\Appointment\Domain\Error\AppointmentTimeInvalid;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotFound;

/**
 * The checks the appointment commands share: the time typed in the dealership's timezone, who may be booked, the rep's
 * free time.
 */
final readonly class AppointmentChecks
{
    public function __construct(
        private AppointmentPeople $people,
        private AppointmentRepository $appointments,
        private Clock $clock,
    ) {
    }

    /**
     * "2026-10-20T15:30" typed at the dealership, as the UTC instant stored.
     *
     * @throws AppointmentTimeInvalid invalid_scheduled_at, or appointment_in_past
     */
    public function futureTime(string $dealershipId, string $local): \DateTimeImmutable
    {
        $zone = new \DateTimeZone($this->people->timezone($dealershipId));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($local), $zone)
            ?: \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', trim($local), $zone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $parsed || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw AppointmentTimeInvalid::unreadable($local);
        }
        $utc = $parsed->setTimezone(new \DateTimeZone('UTC'));
        if ($utc <= $this->clock->now()) {
            throw AppointmentTimeInvalid::inThePast();
        }

        return $utc;
    }

    /**
     * @throws InvalidValue user_not_found
     */
    public function rep(string $dealershipId, string $userId): void
    {
        if (!$this->people->isActiveUser($dealershipId, $userId)) {
            throw new InvalidValue('user_not_found', 'This user is not an active member of the dealership.', ['user_id' => $userId]);
        }
    }

    /**
     * @throws NotFound customer_not_found
     */
    public function customer(string $dealershipId, string $customerId): void
    {
        if (!$this->people->hasCustomer($dealershipId, $customerId)) {
            throw new NotFound('customer_not_found', 'The customer does not exist.', ['id' => $customerId]);
        }
    }

    /**
     * @throws InvalidValue deal_not_found
     */
    public function deal(string $dealershipId, string $dealId, string $customerId): void
    {
        if (!$this->people->dealBelongsToCustomer($dealershipId, $dealId, $customerId)) {
            throw new InvalidValue('deal_not_found', 'This deal does not belong to the customer.', ['deal_id' => $dealId]);
        }
    }

    /**
     * @throws AppointmentOverlap appointment_overlap, naming the appointment in the way
     */
    public function repIsFree(string $dealershipId, string $userId, \DateTimeImmutable $start, int $durationMinutes, ?string $exceptId = null): void
    {
        $end = $start->modify(\sprintf('+%d minutes', $durationMinutes));
        $clash = $this->appointments->overlapping($dealershipId, $userId, $start, $end, $exceptId);
        if ([] !== $clash) {
            throw AppointmentOverlap::with($clash[0]->id());
        }
    }
}
