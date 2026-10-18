<?php

namespace App\Tests\Functional\Appointment;

use App\Appointment\Domain\Event\AppointmentCancelled;
use App\Appointment\Domain\Event\AppointmentConfirmed;
use App\Appointment\Domain\Event\AppointmentNoShow;
use App\Appointment\Domain\Event\AppointmentRescheduled;
use App\Appointment\Domain\Event\AppointmentShown;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * Customers 11-20 and 25-30 have no deal and no seeded appointment; customers 1-10 and 21-24 have a deal (DealSeeder).
 */
final class AppointmentApiTest extends AppointmentTestCase
{
    // ---- book -----------------------------------------------------------------------------------------------

    public function testARepBooksInTheDealershipTimezone(): void
    {
        $this->signInAs('sales');
        $year = (int) new \DateTimeImmutable()->format('Y') + 1;

        $winter = $this->book(11, "$year-01-15T15:30", ['duration_minutes' => 45, 'kind' => 'test_drive', 'note' => ' Bring the trade-in ']);
        $summer = $this->book(12, "$year-07-15T15:30");

        $this->assertStatus(201);
        self::assertSame("$year-01-15 20:30", $this->utc($winter['scheduled_at']), '3:30 PM in Tampa is 20:30 UTC in winter (EST).');
        self::assertSame("$year-07-15 19:30", $this->utc($summer['scheduled_at']), 'And 19:30 UTC in summer (EDT): the dealership\'s clock, not a fixed offset.');
        self::assertSame("$year-01-15 21:15", $this->utc($winter['ends_at']));
        self::assertSame(45, $winter['duration_minutes']);
        self::assertSame('test_drive', $winter['kind']);
        self::assertSame('scheduled', $winter['status']);
        self::assertSame('Bring the trade-in', $winter['note']);
        self::assertSame(60, $summer['duration_minutes'], 'An hour unless said otherwise.');
        self::assertSame('visit', $summer['kind']);
        self::assertSame(['id' => $this->customerId(11), 'name' => 'Lauren Lopez'], $winter['customer']);
        self::assertSame($this->userId('sales'), $winter['user']['id'], 'The rep who books it works it.');
        self::assertNotSame('', $winter['user']['name']);
        self::assertNull($winter['confirmed_at']);
        self::assertNull($winter['shown_at']);

        $read = $this->request('GET', '/api/v1/appointments/'.$winter['id']);
        $this->assertStatus(200);
        self::assertSame($winter, $read);
    }

    public function testAManagerBooksForAnotherRepAndLinksADeal(): void
    {
        $this->signInAs('manager');
        $deal = (string) $this->db()->fetchOne('SELECT id FROM deals WHERE customer_id = ?', [$this->customerId(2)]);

        $appointment = $this->book(2, $this->local(5), ['user_id' => $this->userId('sales2'), 'deal_id' => $deal]);

        $this->assertStatus(201);
        self::assertSame($this->userId('sales2'), $appointment['user']['id']);
        self::assertSame($deal, $appointment['deal_id']);
    }

    public function testWithoutADealIdTheCustomersOpenDealIsLinked(): void
    {
        $this->signInAs('sales');
        $deal = (string) $this->db()->fetchOne('SELECT id FROM deals WHERE customer_id = ?', [$this->customerId(1)]);

        $linked = $this->book(1, $this->local(4));
        $without = $this->book(13, $this->local(4));

        self::assertSame($deal, $linked['deal_id'], 'The appointment belongs to the customer\'s open deal.');
        self::assertNull($without['deal_id'], 'A customer without a deal books without one.');
    }

    public function testBookingMovesTheDealToAppointment(): void
    {
        $this->signInAs('sales');
        self::assertSame('new', $this->stageOf(1), 'Customer 1 starts with a new deal.');

        $this->book(1, $this->local(4));

        $this->assertStatus(201);
        self::assertSame('appointment', $this->stageOf(1), 'Deal listens to AppointmentBooked.');
    }

    public function testATimeInThePastIsRefused(): void
    {
        $this->signInAs('sales');

        $body = $this->book(11, $this->local(-1));

        $this->assertError(422, 'appointment_in_past', $body, 'Nobody can book yesterday.');
        $earlier = $this->book(11, new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE))->modify('-5 minutes')->format('Y-m-d\TH:i'));
        $this->assertError(422, 'appointment_in_past', $earlier, 'Not even a few minutes ago.');
    }

    public function testATimeThatIsNotATimeIsRefused(): void
    {
        $this->signInAs('sales');

        foreach (['tomorrow at 3', '2026-13-45T10:00', '2027-02-30T10:00', '2027-01-15'] as $bad) {
            $body = $this->book(11, $bad);
            $this->assertError(422, 'invalid_scheduled_at', $body, "\"$bad\" is not yyyy-MM-ddTHH:mm.");
        }
    }

    public function testBookRefusesMissingAndBadFields(): void
    {
        $this->signInAs('sales');

        $body = $this->request('POST', '/api/v1/appointments', ['duration_minutes' => 5]);

        $this->assertStatus(422);
        self::assertSame('validation', $body['error']);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('customer_id', $fields);
        self::assertContains('scheduled_at', $fields);
        self::assertContains('duration_minutes', $fields);
        $this->request('POST', '/api/v1/appointments', ['customer_id' => $this->customerId(11), 'scheduled_at' => $this->local(3), 'kind' => 'party']);
        $this->assertStatus(422, 'An unknown kind is not a kind.');
    }

    public function testTheSameRepCannotBeBookedTwiceAtOnce(): void
    {
        $this->signInAs('sales');
        $first = $this->book(11, $this->local(6, '10:00'));
        $this->assertStatus(201);

        $clash = $this->book(12, $this->local(6, '10:30'));
        $this->assertError(409, 'appointment_overlap', $clash, 'Half an hour into the rep\'s hour.');
        self::assertSame($first['id'], $clash['detail']['appointment_id'], 'The refusal names the appointment in the way.');

        $this->book(12, $this->local(6, '09:30'));
        $this->assertStatus(409, 'Ending at 10:30 overlaps the start.');

        $this->book(12, $this->local(6, '11:00'));
        $this->assertStatus(201, 'Back to back is fine.');
        $this->book(13, $this->local(6, '09:00'));
        $this->assertStatus(201, 'Ending exactly when the other starts is fine.');
    }

    public function testAnotherRepIsFreeAtThatTime(): void
    {
        $this->signInAs('sales');
        $this->book(11, $this->local(6, '10:00'));

        $this->book(12, $this->local(6, '10:00'), ['user_id' => $this->userId('sales2')]);

        $this->assertStatus(201, 'The overlap rule is per rep.');
    }

    public function testCancelledAndMissedAppointmentsFreeTheRepsTime(): void
    {
        $this->signInAs('sales');
        $cancelled = $this->book(11, $this->local(7, '10:00'));
        $missed = $this->book(12, $this->local(7, '12:00'));
        $this->request('POST', '/api/v1/appointments/'.$cancelled['id'].'/cancel');
        $this->request('POST', '/api/v1/appointments/'.$missed['id'].'/no-show');

        $this->book(13, $this->local(7, '10:00'));
        $this->assertStatus(201, 'The cancelled slot is free again.');
        $this->book(14, $this->local(7, '12:00'));
        $this->assertStatus(201, 'And so is the one the customer missed.');
    }

    public function testALongAppointmentBlocksTheHoursItCovers(): void
    {
        $this->signInAs('sales');
        $this->book(11, $this->local(8, '09:00'), ['duration_minutes' => 480]);

        $this->book(12, $this->local(8, '16:30'));

        $this->assertStatus(409, 'An all-day delivery covers 16:30.');
    }

    public function testBookRefusesAnUnknownRepCustomerAndDeal(): void
    {
        $this->signInAs('sales');

        $rep = $this->book(11, $this->local(3), ['user_id' => '00000000-0000-7000-8000-000000000000']);
        $this->assertError(422, 'user_not_found', $rep);

        $otherRep = $this->request('POST', '/api/v1/appointments', ['customer_id' => $this->customerId(11), 'scheduled_at' => $this->local(3), 'user_id' => (string) $this->db()->fetchOne('SELECT id FROM users WHERE email = ?', ['other@mustang.test'])]);
        $this->assertError(422, 'user_not_found', $otherRep, 'Another dealership\'s user cannot be booked.');

        $customer = $this->request('POST', '/api/v1/appointments', ['customer_id' => $this->otherCustomerId(1), 'scheduled_at' => $this->local(3)]);
        $this->assertError(404, 'customer_not_found', $customer, 'Another dealership\'s customer does not exist for us.');

        $otherDeal = (string) $this->db()->fetchOne('SELECT id FROM deals WHERE customer_id = ?', [$this->customerId(2)]);
        $deal = $this->book(11, $this->local(3), ['deal_id' => $otherDeal]);
        $this->assertError(422, 'deal_not_found', $deal, 'The deal must be the customer\'s.');
    }

    // ---- confirm, show, no-show, cancel --------------------------------------------------------------------

    public function testTheRepConfirmsAnAppointment(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3));

        $confirmed = $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/confirm');

        $this->assertStatus(200);
        self::assertSame('confirmed', $confirmed['status']);
        self::assertNotNull($confirmed['confirmed_at']);
        self::assertCount(1, $this->published(AppointmentConfirmed::class));
        $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/confirm');
        $this->assertStatus(200, 'Confirming again changes nothing.');
    }

    public function testShowMovesTheDealToVisit(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(1, $this->local(3));
        self::assertSame('appointment', $this->stageOf(1));

        $shown = $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/show');

        $this->assertStatus(200);
        self::assertSame('shown', $shown['status']);
        self::assertNotNull($shown['shown_at']);
        self::assertSame('visit', $this->stageOf(1), 'Deal listens to AppointmentShown.');
        self::assertCount(1, $this->published(AppointmentShown::class));
    }

    public function testNoShowIsRecordedAndPublished(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3));

        $missed = $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/no-show');

        $this->assertStatus(200);
        self::assertSame('no_show', $missed['status']);
        self::assertCount(1, $this->published(AppointmentNoShow::class));
    }

    public function testCancelIsRecordedAndPublished(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3));

        $cancelled = $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/cancel');

        $this->assertStatus(200);
        self::assertSame('cancelled', $cancelled['status']);
        self::assertCount(1, $this->published(AppointmentCancelled::class));
    }

    public function testAnAppointmentThatIsOverCannotBeChangedAgain(): void
    {
        $this->signInAs('sales');
        $shown = $this->book(11, $this->local(3, '09:00'));
        $this->request('POST', '/api/v1/appointments/'.$shown['id'].'/show');
        $cancelled = $this->book(12, $this->local(3, '11:00'));
        $this->request('POST', '/api/v1/appointments/'.$cancelled['id'].'/cancel');

        foreach (['confirm', 'show', 'no-show', 'cancel'] as $move) {
            foreach ([$shown, $cancelled] as $closed) {
                $body = $this->request('POST', '/api/v1/appointments/'.$closed['id'].'/'.$move);
                $this->assertError(409, 'appointment_not_open', $body, "$move on a {$closed['status']} appointment.");
            }
        }
        $body = $this->request('PATCH', '/api/v1/appointments/'.$cancelled['id'], ['note' => 'Too late']);
        $this->assertError(409, 'appointment_not_open', $body, 'A cancelled appointment cannot be edited either.');
    }

    // ---- reschedule -----------------------------------------------------------------------------------------

    public function testRescheduleMovesItAndAsksForANewConfirmation(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '10:00'));
        $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/confirm');

        $moved = $this->request('PATCH', '/api/v1/appointments/'.$appointment['id'], ['scheduled_at' => $this->local(4, '14:00')]);

        $this->assertStatus(200);
        self::assertNotSame($appointment['scheduled_at'], $moved['scheduled_at']);
        self::assertSame('scheduled', $moved['status'], 'The customer has to confirm the new time.');
        self::assertNull($moved['confirmed_at']);
        self::assertCount(1, $this->published(AppointmentRescheduled::class));
    }

    public function testRescheduleCanReassignAndEditWithoutMovingTheTime(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '10:00'));
        $this->request('POST', '/api/v1/appointments/'.$appointment['id'].'/confirm');

        $edited = $this->request('PATCH', '/api/v1/appointments/'.$appointment['id'], ['user_id' => $this->userId('sales2'), 'note' => 'Wants the red one', 'duration_minutes' => 90, 'kind' => 'delivery']);

        $this->assertStatus(200);
        self::assertSame($this->userId('sales2'), $edited['user']['id']);
        self::assertSame([$appointment['scheduled_at'], 'confirmed', 90, 'delivery', 'Wants the red one'], [$edited['scheduled_at'], $edited['status'], $edited['duration_minutes'], $edited['kind'], $edited['note']], 'Only the time resets the confirmation.');
        self::assertCount(0, $this->published(AppointmentRescheduled::class), 'Nothing moved in time, so no new reminder.');
    }

    public function testRescheduleDoesNotClashWithItself(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3, '10:00'));

        $this->request('PATCH', '/api/v1/appointments/'.$appointment['id'], ['scheduled_at' => $this->local(3, '10:30')]);

        $this->assertStatus(200, 'Moving by half an hour overlaps only its old slot.');
    }

    public function testRescheduleIsRefusedIntoThePastOrOntoAnotherAppointment(): void
    {
        $this->signInAs('sales');
        $first = $this->book(11, $this->local(3, '10:00'));
        $second = $this->book(12, $this->local(3, '13:00'));

        $past = $this->request('PATCH', '/api/v1/appointments/'.$second['id'], ['scheduled_at' => $this->local(-1)]);
        $this->assertError(422, 'appointment_in_past', $past);
        $clash = $this->request('PATCH', '/api/v1/appointments/'.$second['id'], ['scheduled_at' => $this->local(3, '10:15')]);
        $this->assertError(409, 'appointment_overlap', $clash);
        self::assertSame($first['id'], $clash['detail']['appointment_id']);
        $reassign = $this->request('PATCH', '/api/v1/appointments/'.$second['id'], ['user_id' => $this->userId('sales2'), 'scheduled_at' => $this->local(3, '10:15')]);
        $this->assertStatus(200, 'Another rep is free at 10:15.');
        $clashWithReassign = $this->request('PATCH', '/api/v1/appointments/'.$second['id'], ['user_id' => $this->userId('sales')]);
        $this->assertError(409, 'appointment_overlap', $clashWithReassign, 'Handing it back to the first rep lands on their 10:00 visit.');
        self::assertSame($this->userId('sales2'), $reassign['user']['id']);
    }

    // ---- list -----------------------------------------------------------------------------------------------

    public function testTheListFiltersByPeriodRepAndCustomer(): void
    {
        $this->signInAs('sales');
        $mine = $this->book(11, $this->local(10, '15:00'));
        $theirs = $this->book(12, $this->local(10, '09:00'), ['user_id' => $this->userId('sales2')]);
        $later = $this->book(11, $this->local(12, '09:00'));
        $day = $this->local(10, '00:00');
        $dayDate = substr($day, 0, 10);
        $nextDate = new \DateTimeImmutable($dayDate)->modify('+1 day')->format('Y-m-d');

        $inDay = $this->request('GET', "/api/v1/appointments?from=$dayDate&to=$nextDate");
        $this->assertStatus(200);
        self::assertSame([$theirs['id'], $mine['id']], array_column($inDay['items'], 'id'), 'A date means midnight at the dealership; the day\'s appointments come in time order.');

        $byRep = $this->request('GET', "/api/v1/appointments?from=$dayDate&to=$nextDate&user_id=".$this->userId('sales2'));
        self::assertSame([$theirs['id']], array_column($byRep['items'], 'id'));

        $byCustomer = $this->request('GET', '/api/v1/appointments?customer_id='.$this->customerId(11));
        self::assertSame([$mine['id'], $later['id']], array_column($byCustomer['items'], 'id'), 'The customer page asks for one customer\'s appointments.');
        self::assertSame('Lauren Lopez', $byCustomer['items'][0]['customer']['name']);
    }

    public function testThePeriodTakesAnInstantToo(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(10, '15:00'));
        $at = new \DateTimeImmutable($appointment['scheduled_at']);

        $before = $this->request('GET', '/api/v1/appointments?'.http_build_query(['from' => $at->modify('-1 hour')->format(\DATE_ATOM), 'to' => $at->format(\DATE_ATOM)]));
        $after = $this->request('GET', '/api/v1/appointments?'.http_build_query(['from' => $at->format(\DATE_ATOM), 'to' => $at->modify('+1 hour')->format(\DATE_ATOM)]));

        self::assertSame([], $before['items'], '"to" is exclusive.');
        self::assertSame([$appointment['id']], array_column($after['items'], 'id'), '"from" is inclusive.');
        $bad = $this->request('GET', '/api/v1/appointments?from=nonsense');
        $this->assertError(422, 'invalid_period', $bad);
    }

    // ---- another dealership ---------------------------------------------------------------------------------

    public function testAnotherDealershipSeesNothingAndGets404(): void
    {
        $this->signInAs('sales');
        $appointment = $this->book(11, $this->local(3));
        $id = $appointment['id'];

        $this->assertNotFoundForOtherDealership('GET', "/api/v1/appointments/$id");
        $this->assertNotFoundForOtherDealership('PATCH', "/api/v1/appointments/$id", ['note' => 'x']);
        $this->assertNotFoundForOtherDealership('POST', "/api/v1/appointments/$id/confirm");
        $this->assertNotFoundForOtherDealership('POST', "/api/v1/appointments/$id/show");
        $this->assertNotFoundForOtherDealership('POST', "/api/v1/appointments/$id/no-show");
        $this->assertNotFoundForOtherDealership('POST', "/api/v1/appointments/$id/cancel");
        $list = $this->request('GET', '/api/v1/appointments?customer_id='.$this->customerId(11));
        self::assertSame([], $list['items'], 'The list is the other dealership\'s own.');
        $row = $this->db()->fetchAssociative('SELECT status FROM appointments WHERE id = ?', [$id]);
        self::assertSame('scheduled', $row['status'] ?? null, 'None of the attempts changed it.');
    }

    public function testSignedOutVisitorsGet401(): void
    {
        $this->request('GET', '/api/v1/appointments');

        $this->assertStatus(401);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function published(string $class): array
    {
        /** @var TraceableMessageBus $bus */
        $bus = static::getContainer()->get('event.bus');

        return array_values(array_filter(
            array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()),
            static fn (object $m): bool => $m instanceof $class,
        ));
    }
}
