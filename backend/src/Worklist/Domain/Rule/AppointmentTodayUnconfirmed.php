<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * An appointment today (the dealership's day) the customer has not confirmed: 80.
 */
final readonly class AppointmentTodayUnconfirmed implements Rule
{
    public const string CODE = 'appointment_today_unconfirmed';
    private const int SCORE = 80;

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        $appointment = $customer->appointmentToday;
        if (null === $appointment || 'scheduled' !== $appointment->status || !$now->isToday($appointment->scheduledAt)) {
            return null;
        }

        return new Reason(
            self::CODE,
            ['scheduled_at' => $appointment->scheduledAt->format(\DATE_ATOM), 'kind' => $appointment->kind],
            self::SCORE,
        );
    }
}
