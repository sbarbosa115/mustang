<?php

namespace App\Appointment\Application\Command;

use App\Appointment\Application\Port\ReminderDetails;
use App\Appointment\Application\Port\ReminderMailer;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\NotFound;

/**
 * Runs on the worker with nobody signed in: the appointment is loaded through the dealership the message names.
 */
final readonly class DeliverReminderHandler implements CommandHandler
{
    public function __construct(
        private AppointmentRepository $appointments,
        private ReminderDetails $details,
        private ReminderMailer $mailer,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeliverReminder $command): void
    {
        try {
            $appointment = $this->appointments->getInDealership($command->appointmentId, $command->dealershipId);
        } catch (NotFound) {
            return;
        }
        if (!$appointment->needsReminderFor($command->scheduledAt)) {
            return;
        }
        $who = $this->details->for($appointment->dealershipId(), $appointment->userId(), $appointment->customerId());
        if (null === $who) {
            return;
        }

        // Marked first: when the mailer throws, the transaction rolls back and the queue retries.
        $appointment->markReminderSent($this->clock->now());
        $when = $appointment->scheduledAt()->setTimezone(new \DateTimeZone($who['timezone']));
        $this->mailer->send(
            $who['rep_email'],
            \sprintf('Appointment at %s with %s', $when->format('g:i A'), $who['customer_name']),
            \sprintf(
                "Hi %s,\n\n%s with %s starts in about an hour: %s (%s).\n\nOpen Mustang to see the customer's record or to confirm the visit.\n",
                $who['rep_name'],
                ucfirst(str_replace('_', ' ', $appointment->kind()->value)),
                $who['customer_name'],
                $when->format('D, M j \a\t g:i A T'),
                $appointment->durationMinutes().' min',
            ),
        );
    }
}
