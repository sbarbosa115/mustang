<?php

namespace App\Tests\Functional\Deal;

use App\Tests\Support\ApiTestCase;

final class DealApiTest extends ApiTestCase
{
    // Customers 25-30 have no seeded deal (DealSeeder), so each test starts them from nothing.

    // ---- create ---------------------------------------------------------------------------------------------

    public function testARepOpensADealForACustomer(): void
    {
        $this->signInAs('sales');
        $vehicle = $this->vehicleId('MM-1005');

        $deal = $this->request('POST', '/api/v1/deals', [
            'customer_id' => $this->customerId(25),
            'vehicle_id' => $vehicle,
            'expected_gross_cents' => 250000,
        ]);

        $this->assertStatus(201);
        self::assertSame('new', $deal['stage'], 'Every deal starts in new.');
        self::assertSame(0, $deal['days_in_stage']);
        self::assertSame(['id' => $this->customerId(25), 'name' => 'Diego Clark'], $deal['customer']);
        self::assertSame($this->userId('sales'), $deal['assignee']['id'], 'Without an assignee, the rep who opens the deal works it.');
        self::assertNotSame('', $deal['assignee']['name']);
        self::assertSame(['id' => $vehicle, 'title' => '2026 Toyota RAV4', 'stock_number' => 'MM-1005'], $deal['vehicle'], 'The vehicle of interest is shown by title and stock number.');
        self::assertSame(250000, $deal['expected_gross_cents']);
        self::assertNull($deal['trade_in']);
        self::assertNotNull($deal['last_activity_at']);

        $read = $this->request('GET', '/api/v1/deals/'.$deal['id']);
        $this->assertStatus(200);
        self::assertSame($deal['id'], $read['id']);

        $customer = $this->request('GET', '/api/v1/customers/'.$this->customerId(25));
        self::assertSame($deal['id'], $customer['open_deal']['id'], 'The customer record shows its open deal.');
    }

    public function testADealCanBeOpenedForSomeoneElseWithAVehicleDescription(): void
    {
        $this->signInAs('manager');

        $deal = $this->request('POST', '/api/v1/deals', [
            'customer_id' => $this->customerId(26),
            'assigned_user_id' => $this->userId('sales2'),
            'vehicle_text' => '  Blue Mustang, manual ',
        ]);

        $this->assertStatus(201);
        self::assertSame($this->userId('sales2'), $deal['assignee']['id']);
        self::assertNull($deal['vehicle']);
        self::assertSame('Blue Mustang, manual', $deal['vehicle_text']);
    }

    public function testACustomerHasOneOpenDealAtATime(): void
    {
        $this->signInAs('sales');
        $first = $this->request('POST', '/api/v1/deals', ['customer_id' => $this->customerId(27)]);
        $this->assertStatus(201);

        $body = $this->request('POST', '/api/v1/deals', ['customer_id' => $this->customerId(27)]);

        $this->assertError(409, 'open_deal_exists', $body, 'A second open deal would split the customer\'s history.');
        self::assertSame($first['id'], $body['detail']['deal_id'], 'The refusal names the deal to open instead.');

        $this->request('POST', '/api/v1/deals/'.$first['id'].'/stage', ['stage' => 'lost', 'lost_reason' => 'Bought elsewhere']);
        $this->assertStatus(200);
        $this->request('POST', '/api/v1/deals', ['customer_id' => $this->customerId(27)]);
        $this->assertStatus(201, 'Once the deal is lost, a new one may be opened.');
    }

    public function testCreateRefusesBadInput(): void
    {
        $this->signInAs('sales');

        $body = $this->request('POST', '/api/v1/deals', ['expected_gross_cents' => -1]);

        $this->assertError(422, 'validation', $body);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('customer_id', $fields);
        self::assertContains('expected_gross_cents', $fields);
    }

    public function testCreateRefusesAnAssigneeOrVehicleOfAnotherDealership(): void
    {
        $this->signInAs('sales');

        $body = $this->request('POST', '/api/v1/deals', ['customer_id' => $this->customerId(28), 'assigned_user_id' => $this->userId('other')]);
        $this->assertError(422, 'assignee_not_found', $body, 'Only an active member of the dealership works its deals.');

        $body = $this->request('POST', '/api/v1/deals', ['customer_id' => $this->customerId(28), 'vehicle_id' => $this->vehicleId('OM-2001')]);
        $this->assertError(422, 'vehicle_not_found', $body, 'Another rooftop\'s inventory is not ours to sell.');
    }

    public function testCreateForAnotherDealershipsCustomerIsNotFound(): void
    {
        $this->signInAs('sales');

        $body = $this->request('POST', '/api/v1/deals', ['customer_id' => $this->otherCustomerId(1)]);

        $this->assertError(404, 'customer_not_found', $body);
    }

    // ---- stage ----------------------------------------------------------------------------------------------

    public function testMovingAStageRestartsDaysInStageAndStampsTheStage(): void
    {
        $this->signInAs('bdc');
        $deal = $this->open(25);

        $moved = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'proposal']);

        $this->assertStatus(200, 'Every role may move a deal forward.');
        self::assertSame('proposal', $moved['stage']);
        self::assertSame(0, $moved['days_in_stage']);
        self::assertNotNull($moved['proposal_at']);

        $back = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'engaged']);
        $this->assertStatus(200, 'And back.');
        self::assertNull($back['proposal_at']);
        self::assertNotNull($back['engaged_at']);
    }

    public function testStageRefusals(): void
    {
        $this->signInAs('sales');
        $deal = $this->open(25);

        $body = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'new']);
        $this->assertError(409, 'stage_unchanged', $body);

        $body = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'lost']);
        $this->assertError(422, 'lost_reason_required', $body, 'A lost deal says why.');

        $body = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'nowhere']);
        $this->assertStatus(422, 'An unknown stage is invalid input.');

        $lost = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'lost', 'lost_reason' => 'Went quiet']);
        $this->assertStatus(200);
        self::assertSame('Went quiet', $lost['lost_reason']);
        self::assertNotNull($lost['lost_at']);

        $body = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'proposal']);
        $this->assertError(409, 'reopen_to_engaged', $body);

        $reopened = $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'engaged']);
        $this->assertStatus(200, 'Reopen goes to engaged.');
        self::assertNull($reopened['lost_reason']);
    }

    public function testALostDealCannotBeReopenedWhileTheCustomerHasAnotherOpenDeal(): void
    {
        $this->signInAs('sales');
        $first = $this->open(29);
        $this->request('POST', '/api/v1/deals/'.$first.'/stage', ['stage' => 'lost', 'lost_reason' => 'Price']);
        $second = $this->open(29);

        $body = $this->request('POST', '/api/v1/deals/'.$first.'/stage', ['stage' => 'engaged']);

        $this->assertError(409, 'open_deal_exists', $body);
        self::assertSame($second, $body['detail']['deal_id']);
    }

    public function testAStageChangeRaisesDealStageChanged(): void
    {
        $this->signInAs('sales');
        // One request to watch (a second request resets the traced buses), so the deal is opened without one.
        $deal = (string) static::getContainer()->get(\App\Shared\Application\Bus\CommandBus::class)->dispatch(new \App\Deal\Application\Command\OpenDeal($this->dealershipId(), $this->customerId(25)));
        $this->client->disableReboot();

        $this->request('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'visit']);
        $this->assertStatus(200);

        /** @var \Symfony\Component\Messenger\TraceableMessageBus $bus */
        $bus = static::getContainer()->get('event.bus');
        $events = array_values(array_filter(
            array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()),
            static fn (object $m): bool => $m instanceof \App\Deal\Domain\Event\DealStageChanged,
        ));
        self::assertCount(1, $events, 'Timeline and Worklist follow stage changes.');
        self::assertSame(['new', 'visit', $this->userId('sales'), $this->customerId(25)], [$events[0]->from, $events[0]->to, $events[0]->actorUserId, $events[0]->customerId]);
    }

    // ---- vehicle, trade-in, assignee ------------------------------------------------------------------------

    public function testTheVehicleOfInterestCanBeSetChangedAndCleared(): void
    {
        $this->signInAs('sales');
        $deal = $this->open(25);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/vehicle', ['vehicle_id' => $this->vehicleId('MM-1002')]);
        $this->assertStatus(200);
        self::assertSame('MM-1002', $body['vehicle']['stock_number']);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/vehicle', ['vehicle_text' => 'Something red']);
        self::assertNull($body['vehicle'], 'The patch sets the whole vehicle of interest.');
        self::assertSame('Something red', $body['vehicle_text']);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/vehicle', ['vehicle_id' => $this->vehicleId('OM-2001')]);
        $this->assertError(422, 'vehicle_not_found', $body);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/vehicle', ['vehicle_id' => 'not-a-uuid']);
        $this->assertError(422, 'validation', $body);
    }

    public function testTheTradeInIsSetAndCleared(): void
    {
        $this->signInAs('sales');
        $deal = $this->open(25);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/trade-in', ['year' => 2017, 'make' => 'Honda', 'model' => 'Civic', 'mileage' => 82000, 'payoff_cents' => 450000]);
        $this->assertStatus(200);
        self::assertSame(['year' => 2017, 'make' => 'Honda', 'model' => 'Civic', 'mileage' => 82000, 'payoff_cents' => 450000], $body['trade_in']);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/trade-in', ['year' => 1800, 'mileage' => -5]);
        $this->assertError(422, 'validation', $body);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/trade-in', []);
        $this->assertStatus(200);
        self::assertNull($body['trade_in'], 'An empty trade-in removes it.');
    }

    public function testAssignToMe(): void
    {
        $this->signInAs('sales');
        $deal = $this->open(25);
        $this->signInAs('manager');

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/assignee', ['user_id' => $this->userId('manager')]);

        $this->assertStatus(200);
        self::assertSame($this->userId('manager'), $body['assignee']['id']);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/assignee', ['user_id' => $this->userId('other')]);
        $this->assertError(422, 'assignee_not_found', $body);

        $body = $this->request('PATCH', '/api/v1/deals/'.$deal.'/assignee', []);
        $this->assertError(422, 'validation', $body);
    }

    // ---- list -----------------------------------------------------------------------------------------------

    public function testTheListFiltersByCustomerStageAssigneeOpenAndName(): void
    {
        $this->signInAs('sales');
        $a = $this->open(25);
        $b = $this->open(26, $this->userId('sales2'));
        $this->request('POST', '/api/v1/deals/'.$b.'/stage', ['stage' => 'lost', 'lost_reason' => 'Price']);

        $byCustomer = $this->request('GET', '/api/v1/deals?customer_id='.$this->customerId(25));
        $this->assertStatus(200);
        self::assertSame([$a], array_column($byCustomer['items'], 'id'), 'The customer page lists that customer\'s deals.');
        self::assertSame(1, $byCustomer['total']);

        $lost = $this->request('GET', '/api/v1/deals?stage=lost&customer_id='.$this->customerId(26));
        self::assertSame([$b], array_column($lost['items'], 'id'));

        $open = $this->request('GET', '/api/v1/deals?open=true&per_page=100');
        self::assertContains($a, array_column($open['items'], 'id'));
        self::assertNotContains($b, array_column($open['items'], 'id'), 'Lost deals are not open.');

        $mine = $this->request('GET', '/api/v1/deals?per_page=100&assigned_to='.$this->userId('sales2'));
        self::assertContains($b, array_column($mine['items'], 'id'));
        self::assertNotContains($a, array_column($mine['items'], 'id'));

        $named = $this->request('GET', '/api/v1/deals?q=diego');
        self::assertSame([$a], array_column($named['items'], 'id'), 'q matches the customer name.');

        $literal = $this->request('GET', '/api/v1/deals?q=%25');
        self::assertSame(0, $literal['total'], 'A % in q matches literally.');
    }

    public function testTheListPaginatesNewestFirst(): void
    {
        $this->signInAs('sales');
        $this->open(25);
        $newest = $this->open(26);

        $page = $this->request('GET', '/api/v1/deals?per_page=1');

        $this->assertStatus(200);
        self::assertSame([$newest], array_column($page['items'], 'id'));
        self::assertSame(1, $page['per_page']);
        self::assertGreaterThanOrEqual(2, $page['total']);
    }

    // ---- tenancy --------------------------------------------------------------------------------------------

    public function testAnotherDealershipGetsNotFoundAndSeesNoRows(): void
    {
        $this->signInAs('sales');
        $deal = $this->open(25);

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/deals/'.$deal);
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/deals/'.$deal.'/stage', ['stage' => 'engaged']);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/deals/'.$deal.'/vehicle', ['vehicle_text' => 'x']);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/deals/'.$deal.'/trade-in', ['year' => 2015]);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/deals/'.$deal.'/assignee', ['user_id' => $this->userId('other')]);

        $list = $this->request('GET', '/api/v1/deals?per_page=100');
        $this->assertStatus(200);
        self::assertNotContains($deal, array_column($list['items'], 'id'));
        $byCustomer = $this->request('GET', '/api/v1/deals?customer_id='.$this->customerId(25));
        self::assertSame(0, $byCustomer['total'], 'Filtering by another rooftop\'s customer finds nothing.');

        $this->signInAs('sales');
        $this->request('GET', '/api/v1/deals/'.$deal);
        $this->assertStatus(200, 'Nothing changed for the owner.');
    }

    public function testDealsNeedASession(): void
    {
        $this->request('GET', '/api/v1/deals');

        $this->assertStatus(401);
    }

    private function open(int $customer, ?string $assignee = null): string
    {
        $body = $this->request('POST', '/api/v1/deals', array_filter(['customer_id' => $this->customerId($customer), 'assigned_user_id' => $assignee]));
        $this->assertStatus(201, 'Opening the deal for the test.');

        return (string) $body['id'];
    }

    private function vehicleId(string $stock): string
    {
        return (string) $this->db()->fetchOne('SELECT id FROM vehicles WHERE stock_number = ?', [$stock]);
    }
}
