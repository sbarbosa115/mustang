<?php

namespace App\Tests\Functional\Timeline;

use App\Appointment\Domain\Event\AppointmentBooked;
use App\Appointment\Domain\Event\AppointmentCancelled;
use App\Appointment\Domain\Event\AppointmentNoShow;
use App\Appointment\Domain\Event\AppointmentRescheduled;
use App\Appointment\Domain\Event\AppointmentShown;
use App\Compliance\Domain\Event\ConsentRecorded;
use App\Compliance\Domain\Event\ConsentRevoked;
use App\Customer\Domain\Event\NoteAdded;
use App\Deal\Domain\Event\DealStageChanged;
use App\Lead\Domain\Event\LeadCaptured;
use App\Messaging\Domain\Event\InboundMessageReceived;
use App\Messaging\Domain\Event\MessageSent;
use App\Shared\Domain\Id;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Each event becomes one timeline entry. Published the way the worker would: nobody signed in, so the dealership
 * and customer can only come from the event. Customers 25-30 have no seeded history.
 */
final class TimelineProjectionTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{0: callable(self): object, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function events(): iterable
    {
        $at = new \DateTimeImmutable('2026-10-01 14:30:00');
        $d = static fn (self $t): string => $t->dealershipId();
        $c = static fn (self $t): string => $t->customerId(25);
        $u = static fn (self $t): string => $t->userId('sales');

        yield 'lead' => [static fn (self $t) => new LeadCaptured(Id::new(), $d($t), $c($t), 'website', null, 'White F-150', $at), 'lead', 'lead_captured', 'lead', 'none'];
        yield 'text out' => [static fn (self $t) => new MessageSent(Id::new(), $d($t), Id::new(), $c($t), 'text', $u($t), $at), 'message_out', 'message_sent_text', 'message', 'sales'];
        yield 'email out' => [static fn (self $t) => new MessageSent(Id::new(), $d($t), Id::new(), $c($t), 'email', null, $at), 'message_out', 'message_sent_email', 'message', 'none'];
        yield 'inbound' => [static fn (self $t) => new InboundMessageReceived(Id::new(), $d($t), Id::new(), $c($t), 'text', '+18135551025', 'Is it still there?', $at), 'message_in', 'message_received_text', 'message', 'none'];
        yield 'stage' => [static fn (self $t) => new DealStageChanged(Id::new(), $d($t), $c($t), 'new', 'engaged', $u($t), $at), 'stage', 'stage_changed_engaged', 'deal', 'sales'];
        yield 'booked' => [static fn (self $t) => new AppointmentBooked(Id::new(), $d($t), $c($t), $u($t), null, $at->modify('+2 days'), $at), 'appointment', 'appointment_booked', 'appointment', 'sales'];
        yield 'rescheduled' => [static fn (self $t) => new AppointmentRescheduled(Id::new(), $d($t), $c($t), $u($t), null, $at->modify('+3 days'), $at), 'appointment', 'appointment_rescheduled', 'appointment', 'sales'];
        yield 'shown' => [static fn (self $t) => new AppointmentShown(Id::new(), $d($t), $c($t), null, $at), 'appointment', 'appointment_shown', 'appointment', 'none'];
        yield 'no show' => [static fn (self $t) => new AppointmentNoShow(Id::new(), $d($t), $c($t), null, $at), 'appointment', 'appointment_no_show', 'appointment', 'none'];
        yield 'cancelled' => [static fn (self $t) => new AppointmentCancelled(Id::new(), $d($t), $c($t), null, $at), 'appointment', 'appointment_cancelled', 'appointment', 'none'];
        yield 'note' => [static fn (self $t) => new NoteAdded(Id::new(), $d($t), $c($t), $u($t), $at), 'note', 'note_added', 'note', 'sales'];
        yield 'consent' => [static fn (self $t) => new ConsentRecorded(Id::new(), $d($t), $c($t), '+18135551025', 'text', 'web_form', $u($t), $at), 'consent', 'consent_recorded_text', 'consent', 'sales'];
        yield 'revoked' => [static fn (self $t) => new ConsentRevoked(Id::new(), $d($t), $c($t), '+18135551025', 'text', 'stop_keyword', null, $at), 'consent', 'consent_revoked_text', 'consent', 'none'];
    }

    /**
     * @param callable(self): object $make
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('events')]
    public function testEachEventBecomesOneEntryWithTheRightKindCodeAndActor(callable $make, string $kind, string $code, string $refType, string $actor): void
    {
        $this->publish($make($this));

        $rows = $this->rows();
        self::assertCount(1, $rows);
        self::assertSame($kind, $rows[0]['kind']);
        self::assertSame($refType, $rows[0]['ref_type']);
        self::assertNotNull($rows[0]['ref_id']);
        self::assertStringStartsWith($code.':', $rows[0]['event_id']);
        self::assertLessThanOrEqual(64, \strlen($rows[0]['event_id']));
        self::assertSame($this->dealershipId(), $rows[0]['dealership_id'], 'The dealership comes from the event.');
        self::assertSame('none' === $actor ? null : $this->userId($actor), $rows[0]['actor_user_id']);
        self::assertSame('2026-10-01 14:30:00', $rows[0]['occurred_at']);
        self::assertNotSame('', $rows[0]['summary']);
    }

    public function testTheSameEventDeliveredTwiceAddsOneEntry(): void
    {
        $event = new MessageSent(Id::new(), $this->dealershipId(), Id::new(), $this->customerId(25), 'text', null, new \DateTimeImmutable('2026-10-01 10:00:00'));

        $this->publish($event);
        $this->publish($event);

        self::assertCount(1, $this->rows(), 'Messenger may redeliver; the entry is added once.');
    }

    public function testTwoStageMovesOfTheSameDealAreTwoEntries(): void
    {
        $deal = Id::new();
        $at = new \DateTimeImmutable('2026-10-01 10:00:00');

        $this->publish(new DealStageChanged($deal, $this->dealershipId(), $this->customerId(25), 'new', 'engaged', null, $at));
        $this->publish(new DealStageChanged($deal, $this->dealershipId(), $this->customerId(25), 'engaged', 'appointment', null, $at));

        self::assertCount(2, $this->rows());
    }

    public function testALongSummaryFitsTheColumn(): void
    {
        $this->publish(new LeadCaptured(Id::new(), $this->dealershipId(), $this->customerId(25), 'website', null, str_repeat('x', 200), new \DateTimeImmutable('2026-10-01 10:00:00')));

        self::assertLessThanOrEqual(255, mb_strlen($this->rows()[0]['summary']));
    }

    private function publish(object $event): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('event.bus');
        $bus->dispatch($event);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->db()->fetchAllAssociative('SELECT * FROM activities WHERE customer_id = ? AND event_id NOT LIKE ?', [$this->customerId(25), '%seed%']);
    }
}
