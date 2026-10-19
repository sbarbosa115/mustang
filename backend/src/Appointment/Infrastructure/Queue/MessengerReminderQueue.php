<?php

namespace App\Appointment\Infrastructure\Queue;

use App\Appointment\Application\Command\SendReminder;
use App\Appointment\Application\Port\ReminderQueue;
use App\Shared\Application\Port\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * SendReminder is an AsyncMessage, so it is routed to the async transport; the DelayStamp holds it until $sendAt.
 */
final readonly class MessengerReminderQueue implements ReminderQueue
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private MessageBusInterface $bus,
        private Clock $clock,
    ) {
    }

    public function queue(string $appointmentId, string $dealershipId, \DateTimeImmutable $scheduledAt, \DateTimeImmutable $sendAt): void
    {
        $delayMs = max(0, ($sendAt->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
        $this->bus->dispatch(new SendReminder($appointmentId, $dealershipId, $scheduledAt), [new DelayStamp($delayMs)]);
    }
}
