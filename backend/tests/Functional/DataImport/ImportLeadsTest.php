<?php

namespace App\Tests\Functional\DataImport;

use App\Lead\Domain\Event\LeadCaptured;

final class ImportLeadsTest extends ImportTestCase
{
    private const HEADER = "first_name,last_name,phone,email,source,vehicle_interest,received_at,sales_rep_email,notes\n";

    /**
     * Uploads and runs the worker; answers the finished job and the LeadCaptured events the worker raised.
     *
     * @return array{0: array<string, mixed>, 1: list<LeadCaptured>}
     */
    private function importLeads(string $contents): array
    {
        $this->signInAs('manager');
        $job = $this->upload('leads', $contents);
        $this->assertStatus(201);
        $this->runWorker();
        $bus = static::getContainer()->get('event.bus');
        $events = array_values(array_filter(
            array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()),
            static fn (object $e): bool => $e instanceof LeadCaptured,
        ));
        $this->signInAs('manager');

        return [$this->request('GET', '/api/v1/imports/'.$job['id']), $events];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadOf(string $customerId): array
    {
        return (array) $this->db()->fetchAssociative('SELECT * FROM leads WHERE customer_id = ?', [$customerId]);
    }

    public function testTheSampleFileOpensALeadPerRowAndEachRaisesLeadCaptured(): void
    {
        $this->signInAs('manager');
        [$job, $events] = $this->importLeads($this->sample('leads'));

        self::assertSame('done', $job['status']);
        self::assertSame([3, 3, 0], [$job['rows_total'], $job['rows_created'], $job['rows_failed']], json_encode($job['errors']) ?: '');
        self::assertCount(3, $events, 'One LeadCaptured per row: on it the Deal context opens the deal.');
        $daniel = $this->customerIdByPhone('+18135557201');
        self::assertNotFalse($daniel, 'The phone is normalised and a customer created for the lead.');
        $lead = $this->leadOf($daniel);
        self::assertSame(['website', 'assigned', '2025 Ford Bronco Sport'], [$lead['source'], $lead['status'], $lead['vehicle_interest_text']]);
        self::assertSame($this->dealershipId(), $lead['dealership_id']);
        $tz = new \DateTimeZone((string) $this->db()->fetchOne('SELECT timezone FROM dealerships WHERE id = ?', [$this->dealershipId()]));
        $expected = (new \DateTimeImmutable('2026-01-15 10:30', $tz))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        self::assertSame($expected, $lead['received_at'], 'received_at is read in the dealership\'s timezone and stored in UTC.');
        self::assertSame('walk_in', $this->leadOf((string) $this->customerIdByEmail('tom.becker@example.com'))['source'], '"walk-in" is the walk_in source.');
        self::assertStringContainsString('Asked about financing', (string) $this->db()->fetchOne('SELECT body FROM customer_notes WHERE customer_id = ?', [$daniel]));
        self::assertContains($daniel, array_map(static fn (LeadCaptured $e): string => $e->customerId, $events));
    }

    public function testTheRepIsFoundByEmailAndAMissingRepGoesToRoundRobin(): void
    {
        [$job] = $this->importLeads(self::HEADER
            ."Rae,Lund,813-555-7701,,phone,,,sales2@mustang.test,\n"
            ."Ola,Berg,813-555-7702,,phone,,,BDC@Mustang.test,\n"
            ."Ivo,Nash,813-555-7703,,phone,,,,\n"
            ."Una,Holt,813-555-7704,,phone,,,,\n"
            ."Kit,Ames,813-555-7705,,phone,,,nobody@mustang.test,\n");

        self::assertSame(5, $job['rows_created']);
        self::assertSame($this->userId('sales2'), $this->leadOf((string) $this->customerIdByPhone('+18135557701'))['assigned_user_id'], 'sales_rep_email names the rep.');
        self::assertSame($this->userId('sales2'), $this->db()->fetchOne('SELECT sales1_user_id FROM customers WHERE id = ?', [$this->customerIdByPhone('+18135557701')]), 'A new customer gets the lead\'s salesperson as Sales 1.');
        self::assertSame($this->userId('bdc'), $this->leadOf((string) $this->customerIdByPhone('+18135557702'))['assigned_user_id'], 'A BDC agent can take a lead; emails match in any case.');
        $sales = [$this->userId('sales'), $this->userId('sales2')];
        $third = $this->leadOf((string) $this->customerIdByPhone('+18135557703'))['assigned_user_id'];
        $fourth = $this->leadOf((string) $this->customerIdByPhone('+18135557704'))['assigned_user_id'];
        self::assertContains($third, $sales, 'No rep: round robin over the active salespeople.');
        self::assertContains($fourth, $sales);
        self::assertNotSame($third, $fourth, 'Round robin takes turns.');
        self::assertContains($this->leadOf((string) $this->customerIdByPhone('+18135557705'))['assigned_user_id'], $sales, 'A rep email nobody has goes to round robin too.');
    }

    public function testAnExistingCustomerIsMatchedAndASecondRunOpensNoSecondLead(): void
    {
        $seeded = $this->customerId(4);
        $file = self::HEADER
            .",,(813) 555-1004,,website,Explorer,,,\n"
            ."New,Person,813-555-7801,,,Maverick,,,\n";
        [$first, $events] = $this->importLeads($file);

        self::assertSame(2, $first['rows_created'], json_encode($first['errors']) ?: '');
        self::assertCount(2, $events);
        self::assertSame('Explorer', $this->leadOf($seeded)['vehicle_interest_text'], 'The seeded customer is matched by phone; no new customer.');
        self::assertSame('import', $this->leadOf((string) $this->customerIdByPhone('+18135557801'))['source'], 'No source column value: the lead came from the import.');

        $leads = (int) $this->db()->fetchOne('SELECT COUNT(*) FROM leads');
        [$second, $again] = $this->importLeads($file);
        self::assertSame([0, 2], [$second['rows_created'], $second['rows_skipped']], 'The customer already has an open lead: it is not opened twice.');
        self::assertSame($leads, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM leads'));
        self::assertSame([], $again);
    }

    public function testBadRowsAreReported(): void
    {
        [$job] = $this->importLeads(self::HEADER
            ."Fut,Ure,813-555-7901,,,,2099-01-01,,\n"
            ."Bad,Date,813-555-7902,,,,31/31/2025,,\n"
            ."No,Contact,,,,,,,\n"
            ."Odd,Source,813-555-7903,,AutoTrader,,01/20/2026 9:15 AM,,\n");

        self::assertSame([4, 1, 3], [$job['rows_total'], $job['rows_created'], $job['rows_failed']], json_encode($job['errors']) ?: '');
        self::assertSame(['received_at', 'received_at', 'phone'], array_column($job['errors'], 'field'));
        $odd = $this->leadOf((string) $this->customerIdByPhone('+18135557903'));
        self::assertSame(['other', 'AutoTrader'], [$odd['source'], $odd['channel_note']], 'An unknown source is "other", with the original kept as the channel note.');
        self::assertFalse($this->customerIdByPhone('+18135557901'), 'A failed row creates no customer.');
    }
}
