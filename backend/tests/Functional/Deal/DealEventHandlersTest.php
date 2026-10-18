<?php

namespace App\Tests\Functional\Deal;

use App\Appointment\Domain\Event\AppointmentBooked;
use App\Appointment\Domain\Event\AppointmentShown;
use App\Deal\Domain\Event\DealOpened;
use App\Deal\Domain\Event\DealStageChanged;
use App\Lead\Domain\Event\LeadCaptured;
use App\Messaging\Domain\Event\InboundMessageReceived;
use App\Messaging\Domain\Event\MessageSent;
use App\Shared\Domain\Id;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * The events Deal reacts to, published the way the worker or a console command would: with nobody signed in, so the
 * dealership can only come from the event. Customers 25-30 have no seeded deal.
 */
final class DealEventHandlersTest extends ApiTestCase
{
    private static ?\DateTimeImmutable $now = null;

    // ---- LeadCaptured ---------------------------------------------------------------------------------------

    public function testACapturedLeadOpensADealInNew(): void
    {
        $lead = Id::new();

        $this->publish(new LeadCaptured($lead, $this->dealershipId(), $this->customerId(25), 'website', $this->userId('sales2'), 'White F-150', self::at('-1 hour')));

        $deals = $this->dealsOf(25);
        self::assertCount(1, $deals, 'One lead, one deal.');
        self::assertSame(['new', $lead, $this->userId('sales2'), 'White F-150'], [$deals[0]['stage'], $deals[0]['lead_id'], $deals[0]['assigned_user_id'], $deals[0]['vehicle_text']]);
        self::assertSame(self::at('-1 hour')->format('Y-m-d H:i:s'), $deals[0]['stage_entered_at'], 'Days in stage count from when the lead came in.');
        self::assertCount(1, $this->dispatched(DealOpened::class));
    }

    public function testTheSameLeadDeliveredTwiceOpensOneDeal(): void
    {
        $event = new LeadCaptured(Id::new(), $this->dealershipId(), $this->customerId(25), 'phone', null, null, self::at('-1 hour'));

        $this->publish($event);
        $this->publish($event);

        self::assertCount(1, $this->dealsOf(25), 'Messenger may redeliver; the deal is opened once.');
    }

    public function testALeadForACustomerWithAnOpenDealJoinsThatDeal(): void
    {
        $this->publish(new LeadCaptured(Id::new(), $this->dealershipId(), $this->customerId(25), 'website', null, null, self::at('-2 days')));
        $second = Id::new();

        $this->publish(new LeadCaptured($second, $this->dealershipId(), $this->customerId(25), 'walk_in', null, null, self::at('-1 hour')));

        $deals = $this->dealsOf(25);
        self::assertCount(1, $deals, 'A returning customer keeps one open deal.');
        self::assertSame(self::at('-1 hour')->format('Y-m-d H:i:s'), $deals[0]['last_activity_at'], 'The new enquiry counts as activity.');
    }

    public function testALeadForAnotherDealershipsCustomerOpensNothing(): void
    {
        $count = fn (): int => (int) $this->db()->fetchOne('SELECT COUNT(*) FROM deals WHERE customer_id = ?', [$this->otherCustomerId(1)]);
        $before = $count();

        $this->publish(new LeadCaptured(Id::new(), $this->dealershipId(), $this->otherCustomerId(1), 'website', null, null, self::at('-1 hour')));

        self::assertSame($before, $count(), 'The event\'s dealership and customer must match.');
    }

    public function testAnInactiveOrUnknownAssigneeLeavesTheDealUnassigned(): void
    {
        $this->publish(new LeadCaptured(Id::new(), $this->dealershipId(), $this->customerId(25), 'website', $this->userId('other'), null, self::at('-1 hour')));

        self::assertNull($this->dealsOf(25)[0]['assigned_user_id']);
    }

    // ---- messages -------------------------------------------------------------------------------------------

    public function testAnInboundMessageEngagesADealInNew(): void
    {
        $this->openFor(25);

        $this->publish(new InboundMessageReceived(Id::new(), $this->dealershipId(), Id::new(), $this->customerId(25), 'text', '+18135551025', 'Is it still there?', self::at('-5 minutes')));

        $deal = $this->dealsOf(25)[0];
        self::assertSame('engaged', $deal['stage']);
        self::assertSame(self::at('-5 minutes')->format('Y-m-d H:i:s'), $deal['engaged_at']);
        $changes = $this->dispatched(DealStageChanged::class);
        self::assertCount(1, $changes);
        self::assertSame(['new', 'engaged', null], [$changes[0]->from, $changes[0]->to, $changes[0]->actorUserId], 'Nobody moved it: the customer wrote.');
    }

    public function testASentMessageEngagesADealInNewAndNamesTheSender(): void
    {
        $this->openFor(25);

        $this->publish(new MessageSent(Id::new(), $this->dealershipId(), Id::new(), $this->customerId(25), 'email', $this->userId('bdc'), self::at('-5 minutes')));

        self::assertSame('engaged', $this->dealsOf(25)[0]['stage']);
        self::assertSame($this->userId('bdc'), $this->dispatched(DealStageChanged::class)[0]->actorUserId);
    }

    public function testAMessageOnALaterStageOnlyCountsAsActivity(): void
    {
        $deal = $this->openFor(25);
        $this->db()->executeStatement("UPDATE deals SET stage = 'proposal', last_activity_at = ? WHERE id = ?", [self::at('-3 days')->format('Y-m-d H:i:s'), $deal]);

        $this->publish(new MessageSent(Id::new(), $this->dealershipId(), Id::new(), $this->customerId(25), 'text', $this->userId('sales'), self::at('-1 minute')));

        $row = $this->dealsOf(25)[0];
        self::assertSame('proposal', $row['stage'], 'A message never moves a deal back.');
        self::assertSame(self::at('-1 minute')->format('Y-m-d H:i:s'), $row['last_activity_at'], 'It keeps the deal from looking idle.');
        self::assertCount(0, $this->dispatched(DealStageChanged::class));
    }

    public function testAMessageForACustomerWithoutAnOpenDealChangesNothing(): void
    {
        $this->publish(new InboundMessageReceived(Id::new(), $this->dealershipId(), Id::new(), $this->customerId(26), 'text', '+18135551026', 'Hi', self::at('-1 minute')));

        self::assertCount(0, $this->dealsOf(26), 'A message alone does not open a deal.');
    }

    public function testAMessageOfAnotherDealershipLeavesOurDealAlone(): void
    {
        $this->openFor(25);

        $this->publish(new InboundMessageReceived(Id::new(), $this->dealershipId('other'), Id::new(), $this->customerId(25), 'text', '+18135551025', 'Hi', self::at('-1 minute')));

        self::assertSame('new', $this->dealsOf(25)[0]['stage']);
    }

    // ---- appointments ---------------------------------------------------------------------------------------

    public function testABookedAppointmentMovesTheDealToAppointment(): void
    {
        $deal = $this->openFor(25);

        $this->publish(new AppointmentBooked(Id::new(), $this->dealershipId(), $this->customerId(25), $this->userId('sales'), $deal, self::at('+1 day'), self::at('-1 minute')));

        $row = $this->dealsOf(25)[0];
        self::assertSame('appointment', $row['stage'], 'Booking skips straight from new to appointment.');
        self::assertSame(self::at('-1 minute')->format('Y-m-d H:i:s'), $row['appointment_at']);
    }

    public function testABookingWithoutADealMovesTheCustomersOpenDeal(): void
    {
        $this->openFor(25, 'engaged');

        $this->publish(new AppointmentBooked(Id::new(), $this->dealershipId(), $this->customerId(25), $this->userId('sales'), null, self::at('+1 day'), self::at('-1 minute')));

        self::assertSame('appointment', $this->dealsOf(25)[0]['stage']);
    }

    public function testAShownAppointmentMovesTheDealToVisit(): void
    {
        $deal = $this->openFor(25, 'appointment');

        $this->publish(new AppointmentShown(Id::new(), $this->dealershipId(), $this->customerId(25), $deal, self::at('-1 minute')));

        $row = $this->dealsOf(25)[0];
        self::assertSame('visit', $row['stage']);
        self::assertNotNull($row['visited_at']);
    }

    public function testAppointmentsNeverMoveADealBackOrReopenIt(): void
    {
        $proposal = $this->openFor(25, 'proposal');
        $this->publish(new AppointmentBooked(Id::new(), $this->dealershipId(), $this->customerId(25), $this->userId('sales'), $proposal, self::at('+1 day'), self::at('-1 minute')));
        self::assertSame('proposal', $this->dealsOf(25)[0]['stage']);

        $lost = $this->openFor(26, 'lost');
        $this->publish(new AppointmentShown(Id::new(), $this->dealershipId(), $this->customerId(26), $lost, self::at('-1 minute')));
        self::assertSame('lost', $this->dealsOf(26)[0]['stage'], 'A lost deal is reopened by a person, not by an event.');
    }

    public function testAnotherDealershipsDealIdIsIgnored(): void
    {
        $ours = $this->openFor(25);

        $this->publish(new AppointmentBooked(Id::new(), $this->dealershipId('other'), $this->otherCustomerId(2), $this->userId('other'), $ours, self::at('+1 day'), self::at('-1 minute')));

        self::assertSame('new', $this->dealsOf(25)[0]['stage'], 'An event only reaches deals of its own dealership.');
    }

    private function publish(object $event): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('event.bus');
        $bus->dispatch($event);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function dispatched(string $class): array
    {
        /** @var TraceableMessageBus $bus */
        $bus = static::getContainer()->get('event.bus');

        return array_values(array_filter(
            array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()),
            static fn (object $m): bool => $m instanceof $class,
        ));
    }

    /**
     * A deal for the customer, written straight into the table, in the given stage.
     */
    private function openFor(int $customer, string $stage = 'new'): string
    {
        $id = Id::new();
        $this->db()->executeStatement(
            'INSERT INTO deals (id, dealership_id, customer_id, stage, stage_entered_at, lost_reason, last_activity_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW() - INTERVAL 2 DAY, ?, NOW() - INTERVAL 2 DAY, NOW(), NOW())',
            [$id, $this->dealershipId(), $this->customerId($customer), $stage, 'lost' === $stage ? 'Price' : null],
        );

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dealsOf(int $customer): array
    {
        /** @var list<array<string, mixed>> */
        return $this->db()->fetchAllAssociative('SELECT * FROM deals WHERE customer_id = ? ORDER BY created_at, id', [$this->customerId($customer)]);
    }

    private static function at(string $relative): \DateTimeImmutable
    {
        // Whole seconds, so a value read back from the database compares equal.
        self::$now ??= new \DateTimeImmutable('@'.time());

        return self::$now->modify($relative);
    }
}
