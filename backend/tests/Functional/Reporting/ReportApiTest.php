<?php

namespace App\Tests\Functional\Reporting;

use App\Shared\Domain\Id;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class ReportApiTest extends ApiTestCase
{
    use KpiFixtures;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, array<string, mixed>>
     */
    private function kpisByCode(array $response): array
    {
        $byCode = [];
        foreach ($response['kpis'] as $kpi) {
            $byCode[$kpi['code']] = $kpi;
        }

        return $byCode;
    }

    public function testOnlyAManagerReadsTheReport(): void
    {
        foreach (['sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $body = $this->request('GET', '/api/v1/reports/kpis');
            $this->assertError(403, 'forbidden', $body, "$role must not read KPIs.");
        }
        $this->signInAs('manager');
        $this->request('GET', '/api/v1/reports/kpis');
        $this->assertStatus(200);
    }

    public function testTheReportShapeAndTheDeltaOnFixedData(): void
    {
        $this->scenario($this->dealershipId());
        $this->signInAs('manager');

        $body = $this->request('GET', '/api/v1/reports/kpis?from=2025-03-08&to=2025-03-10');

        $this->assertStatus(200);
        self::assertSame(['from' => '2025-03-08', 'to' => '2025-03-10'], $body['period']);
        self::assertSame(['from' => '2025-03-05', 'to' => '2025-03-07'], $body['previous']);
        self::assertSame(
            ['first_response_minutes', 'leads_followed', 'lead_to_contact', 'contact_to_appointment', 'appointment_to_show', 'show_to_sale', 'units_per_salesperson'],
            array_column($body['kpis'], 'code'),
        );
        $kpis = $this->kpisByCode($body);
        self::assertEquals(25.0, $kpis['first_response_minutes']['value']);
        self::assertNull($kpis['first_response_minutes']['previous_value'], 'A null, not a 0.');
        self::assertSame('minutes', $kpis['first_response_minutes']['unit']);
        self::assertEquals(50.0, $kpis['leads_followed']['value']);
        self::assertEquals(0.0, $kpis['leads_followed']['previous_value']);
        self::assertNotNull($kpis['leads_followed']['previous_value'], 'Zero followed of 2 leads is 0%, not no data.');
        self::assertSame('percent', $kpis['leads_followed']['unit']);
        self::assertSame('units', $kpis['units_per_salesperson']['unit']);
        self::assertSame(
            ['leads' => 4, 'contacted' => 2, 'appointments' => 1, 'shows' => 1, 'sales' => 1],
            array_column($body['funnel'], 'count', 'code'),
        );
    }

    public function testWithoutDatesItIsTheLastThirtyDaysEndingToday(): void
    {
        // 2025-03-10 23:30 in New York is already the 11th in UTC: "today" is the dealership's day.
        Clock::set(new MockClock('2025-03-11 03:30:00 UTC'));
        $this->signInAs('manager');

        $body = $this->request('GET', '/api/v1/reports/kpis');

        $this->assertStatus(200);
        self::assertSame(['from' => '2025-02-09', 'to' => '2025-03-10'], $body['period']);
        self::assertSame(['from' => '2025-01-10', 'to' => '2025-02-08'], $body['previous']);
    }

    public function testBadDatesAre422(): void
    {
        $this->signInAs('manager');

        $body = $this->request('GET', '/api/v1/reports/kpis?from=nope');

        $this->assertError(422, 'invalid_period', $body);
    }

    public function testAnotherDealershipSeesItsOwnNumbers(): void
    {
        $this->scenario($this->dealershipId());
        $this->lead($this->dealershipId('other'), Id::new(), '2025-03-09 15:00:00');

        $this->signInAs('manager');
        $mine = $this->request('GET', '/api/v1/reports/kpis?from=2025-03-08&to=2025-03-10');
        $this->signInAs('other');
        $theirs = $this->request('GET', '/api/v1/reports/kpis?from=2025-03-08&to=2025-03-10');

        self::assertSame(4, array_column($mine['funnel'], 'count', 'code')['leads']);
        self::assertSame(1, array_column($theirs['funnel'], 'count', 'code')['leads']);
        self::assertEquals(0.0, $this->kpisByCode($theirs)['lead_to_contact']['value'], 'Their one lead was not contacted.');
        self::assertNull($this->kpisByCode($theirs)['contact_to_appointment']['value']);
    }
}
