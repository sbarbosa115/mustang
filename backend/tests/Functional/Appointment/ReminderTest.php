<?php

namespace App\Tests\Functional\Appointment;

use App\Appointment\Application\Command\SendReminder;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The rep's reminder: queued with a delay when an appointment is booked or moved, sent by the worker with nobody
 * signed in. The tests play the worker by handing the queued envelope back to the bus.
 */
final class ReminderTest extends AppointmentTestCase
{
    /** @var array<int, Envelope> */
    private array $queuedReminders = [];
    /** @var array<int, Envelope> */
    private array $queuedMail = [];

    /**
     * The in-memory queues are reset when the next request starts, so what each request queued is kept here.
     *
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        $response = parent::request($method, $path, $body);
        $this->collect();

        return $response;
    }

    private function collect(): void
    {
        foreach (['async' => &$this->queuedReminders, 'mail' => &$this->queuedMail] as $name => &$kept) {
            /** @var InMemoryTransport $queue */
            $queue = static::getContainer()->get('messenger.transport.'.$name);
            foreach ($queue->getSent() as $envelope) {
                $kept[spl_object_id($envelope)] = $envelope;
            }
        }
    }

    public function testBookingQueuesAReminderAnHourBeforeTheStart(): void
    {
        $this->signInAs('sales');

        $appointment = $this->book(11, $this->local(3, '15:00'));

        $this->assertStatus(201);
        $queued = $this->reminders();
        self::assertCount(1, $queued, 'One reminder per appointment.');
        $message = $queued[0]->getMessage();
        self::assertInstanceOf(SendReminder::class, $message);
        self::assertSame([$appointment['id'], $this->dealershipId()], [$message->appointmentId, $message->dealershipId]);
        self::assertSame($this->utc($appointment['scheduled_at']), $message->scheduledAt->format('Y-m-d H:i'), 'The message remembers the time it was queued for.');

        $delay = $queued[0]->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $delay);
        $expected = (new \DateTimeImmutable($appointment['scheduled_at'])->getTimestamp() - 3600 - time()) * 1000;
        self::assertEqualsWithDelta($expected, $delay->getDelay(), 5000, 'It is delayed until 60 minutes before the appointment.');
    }

    public function testAnAppointmentStartingWithinTheHourGetsNoReminder(): void
    {
        $this->signInAs('sales');
        $soon = new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE))->modify('+30 minutes')->format('Y-m-d\TH:i');

        $this->book(11, $soon, ['user_id' => $this->userId('manager')]);

        $this->assertStatus(201);
        self::assertSame([], $this->reminders(), 'The reminder time has passed, so there is nothing to wait for.');
    }

    public function testTheWorkerEmailsTheRepAndItArrivesInMailpit(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '15:00'));
        $this->work($this->reminders()[0]);

        $row = $this->db()->fetchAssociative('SELECT reminder_sent_at FROM appointments WHERE id = ?', [$appointment['id']]);
        self::assertNotNull($row['reminder_sent_at'] ?? null, 'The appointment remembers that the rep was reminded.');
        $mail = $this->mail();
        self::assertCount(1, $mail, 'One email on the mail queue.');
        /** @var SendEmailMessage $message */
        $message = $mail[0]->getMessage();
        $email = $message->getMessage();
        self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $email);
        self::assertSame('sales@mustang.test', $email->getTo()[0]->getAddress(), 'The reminder goes to the rep.');
        self::assertSame('no-reply@mustang.test', $email->getFrom()[0]->getAddress());
        self::assertSame('Appointment at 3:00 PM with Lauren Lopez', $email->getSubject(), 'The time is the dealership\'s clock.');
        self::assertStringContainsString('starts in about an hour', (string) $email->getTextBody());

        // What the mail worker does: send it over SMTP to Mailpit.
        $subject = $email->getSubject();
        static::getContainer()->get(MessageBusInterface::class)->dispatch($mail[0]->with(new ReceivedStamp('mail')));
        $search = json_decode((string) file_get_contents('http://mailpit:8025/api/v1/search?query='.rawurlencode('to:sales@mustang.test subject:"'.$subject.'"')), true);
        self::assertGreaterThanOrEqual(1, $search['messages_count'] ?? 0, 'The email lands in Mailpit.');
    }

    public function testNothingIsSentAfterACancellation(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '15:00'));
        $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/cancel');

        $this->work($this->reminders()[0]);

        self::assertSame([], $this->mail(), 'Nobody is told about a visit that is off.');
        self::assertNull($this->db()->fetchOne('SELECT reminder_sent_at FROM appointments WHERE id = ?', [$appointment['id']]));
    }

    public function testARescheduledAppointmentRemindsOnceForTheNewTime(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '15:00'));
        $this->request('PATCH', '/api/v1/appointments/'.$appointment['id'], ['scheduled_at' => $this->local(4, '09:00')]);
        $queued = $this->reminders();
        self::assertCount(2, $queued, 'The move queued a second reminder.');

        $this->work($queued[0]);
        self::assertSame([], $this->mail(), 'The message for the old time is skipped.');
        $this->work($queued[1]);
        self::assertCount(1, $this->mail(), 'The one for the new time is sent.');
        $email = $this->mail()[0]->getMessage()->getMessage();
        self::assertSame('Appointment at 9:00 AM with Lauren Lopez', $email->getSubject());
    }

    public function testTheSameReminderIsNeverSentTwice(): void
    {
        $this->signInAs('sales');
        $this->book(11, $this->local(3, '15:00'));
        $envelope = $this->reminders()[0];

        $this->work($envelope);
        $this->work($envelope);

        self::assertCount(1, $this->mail(), 'Messenger may redeliver; the rep is reminded once.');
    }

    public function testTheReminderGoesToTheRepWhoHasTheAppointmentNow(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '15:00'));
        $this->request('PATCH', '/api/v1/appointments/'.$appointment['id'], ['user_id' => $this->userId('sales2')]);

        $this->work($this->reminders()[0]);

        self::assertSame('sales2@mustang.test', $this->mail()[0]->getMessage()->getMessage()->getTo()[0]->getAddress(), 'Reassigned, so the new rep is told.');
    }

    public function testAMessageNamingAnotherDealershipSendsNothing(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '15:00'));
        $message = $this->reminders()[0]->getMessage();
        self::assertInstanceOf(SendReminder::class, $message);
        $wrong = new SendReminder($message->appointmentId, $this->dealershipId('other'), $message->scheduledAt);

        $this->work(new Envelope($wrong));

        self::assertSame([], $this->mail(), 'The worker loads the appointment through the dealership the message names.');
        self::assertNull($this->db()->fetchOne('SELECT reminder_sent_at FROM appointments WHERE id = ?', [$appointment['id']]));
    }

    public function testAMessageForAnAppointmentThatIsGoneIsDropped(): void
    {
        $this->work(new Envelope(new SendReminder('00000000-0000-7000-8000-000000000000', $this->dealershipId(), new \DateTimeImmutable('+2 days'))));

        self::assertSame([], $this->mail());
    }

    /**
     * @return list<Envelope>
     */
    private function reminders(): array
    {
        return array_values(array_filter($this->queuedReminders, static fn (Envelope $e): bool => $e->getMessage() instanceof SendReminder));
    }

    /**
     * @return list<Envelope>
     */
    private function mail(): array
    {
        return array_values(array_filter($this->queuedMail, static fn (Envelope $e): bool => $e->getMessage() instanceof SendEmailMessage));
    }

    /**
     * What the worker does with a delivered message: hand it to the bus as received, with nobody signed in.
     */
    private function work(Envelope $envelope): void
    {
        static::getContainer()->get('event.bus')->dispatch($envelope->with(new ReceivedStamp('async')));
        $this->collect();
    }
}
