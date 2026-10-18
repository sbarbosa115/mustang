<?php

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\ApiTestCase;

final class VehicleApiTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function body(string $stock, array $overrides = []): array
    {
        return $overrides + [
            'stock_number' => $stock,
            'year' => 2025,
            'make' => 'Ford',
            'model' => 'Bronco',
            'condition' => 'new',
            'price_cents' => 5200000,
        ];
    }

    private function vehicleId(string $stock): string
    {
        return (string) $this->db()->fetchOne('SELECT id FROM vehicles WHERE stock_number = ?', [$stock]);
    }

    /** @return list<string> */
    private function stocks(string $query): array
    {
        return array_column($this->request('GET', '/api/v1/vehicles?per_page=100&'.$query)['items'], 'stock_number');
    }

    public function testASalespersonAddsAVehicleAndReadsItBack(): void
    {
        $this->signInAs('sales');
        $created = $this->request('POST', '/api/v1/vehicles', self::body('T-1', ['vin' => '1FMEE5DH0NLA00001', 'trim' => 'Big Bend', 'mileage' => 12]));

        $this->assertStatus(201);
        self::assertSame('2025 Ford Bronco', $created['title']);
        self::assertSame('available', $created['status'], 'A new vehicle is for sale until someone says otherwise.');
        self::assertSame(5200000, $created['price_cents']);
        $read = $this->request('GET', '/api/v1/vehicles/'.$created['id']);
        $this->assertStatus(200);
        self::assertSame('Big Bend', $read['trim']);
    }

    public function testAStockNumberIsUniquePerDealership(): void
    {
        $this->signInAs('sales');
        $this->request('POST', '/api/v1/vehicles', self::body('MM-1001'));

        $this->assertError(409, 'stock_number_taken', $this->decoded(), 'MM-1001 is seeded.');
    }

    public function testTwoDealershipsMayUseTheSameStockNumber(): void
    {
        $this->signInAs('other');
        $this->request('POST', '/api/v1/vehicles', self::body('MM-1001'));

        $this->assertStatus(201, 'Stock numbers are unique inside a dealership, not across the platform.');
    }

    public function testRequiredFieldsAreValidated(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/vehicles', ['stock_number' => '', 'year' => 1800]);

        $this->assertStatus(422);
        $fields = array_column($body['violations'], 'field');
        foreach (['stock_number', 'year', 'make', 'model', 'condition'] as $field) {
            self::assertContains($field, $fields, $field.' is required or out of range.');
        }
    }

    public function testAnEditChangesOnlyWhatWasSent(): void
    {
        $this->signInAs('sales');
        $id = $this->vehicleId('MM-1001');
        $before = $this->request('GET', '/api/v1/vehicles/'.$id);
        $after = $this->request('PATCH', '/api/v1/vehicles/'.$id, ['price_cents' => 1234500, 'description' => '']);

        $this->assertStatus(200);
        self::assertSame(1234500, $after['price_cents']);
        self::assertNull($after['description'], 'An empty text clears an optional field.');
        self::assertSame($before['make'], $after['make'], 'Fields left out stay as they were.');
        self::assertSame($before['stock_number'], $after['stock_number'], 'The stock number is not editable.');
    }

    public function testStatusChangesThroughItsOwnEndpoint(): void
    {
        $this->signInAs('sales');
        $id = $this->vehicleId('MM-1001');

        $sold = $this->request('POST', '/api/v1/vehicles/'.$id.'/status', ['status' => 'sold']);
        $this->assertStatus(200);
        self::assertSame('sold', $sold['status']);

        $this->request('POST', '/api/v1/vehicles/'.$id.'/status', ['status' => 'maybe']);
        $this->assertStatus(422, 'An unknown status is refused.');
    }

    public function testAnotherDealershipGets404OnEveryVehicleRoute(): void
    {
        $id = $this->vehicleId('MM-1001');
        $this->assertNotFoundForOtherDealership('GET', '/api/v1/vehicles/'.$id);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/vehicles/'.$id, ['price_cents' => 1]);
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/vehicles/'.$id.'/status', ['status' => 'sold']);
        self::assertSame('available', $this->db()->fetchOne('SELECT status FROM vehicles WHERE stock_number = ?', ['MM-1001']), 'The refused calls changed nothing.');
    }

    public function testAnotherDealershipSeesOnlyItsOwnVehicles(): void
    {
        $this->signInAs('other');

        self::assertSame(['OM-2001', 'OM-2002', 'OM-2003'], $this->sorted($this->stocks('')));
        $facets = $this->request('GET', '/api/v1/vehicles/facets');
        self::assertSame(['Kia', 'Mazda', 'Subaru'], array_column($facets['makes'], 'value'), 'Facets never count another rooftop.');
    }

    public function testTheListIsPaginated(): void
    {
        $this->signInAs('sales');
        $first = $this->request('GET', '/api/v1/vehicles?per_page=15&page=1');
        $third = $this->request('GET', '/api/v1/vehicles?per_page=15&page=3');

        self::assertSame(40, $first['total']);
        self::assertCount(15, $first['items']);
        self::assertCount(10, $third['items']);
        self::assertSame([], array_intersect(array_column($first['items'], 'id'), array_column($third['items'], 'id')));
        $this->request('GET', '/api/v1/vehicles?per_page=500');
        $this->assertStatus(422, 'per_page is capped at 100.');
    }

    public function testFiltersNarrowTheList(): void
    {
        $this->signInAs('sales');

        $new = $this->request('GET', '/api/v1/vehicles?per_page=100&condition=new');
        self::assertGreaterThan(0, $new['total']);
        self::assertSame(['new'], array_values(array_unique(array_column($new['items'], 'condition'))));

        $teslas = $this->request('GET', '/api/v1/vehicles?per_page=100&make=Tesla&model=Model 3');
        self::assertSame(['Tesla'], array_values(array_unique(array_column($teslas['items'], 'make'))));

        foreach ($this->request('GET', '/api/v1/vehicles?per_page=100&price_min=30000&price_max=40000')['items'] as $v) {
            self::assertGreaterThanOrEqual(3000000, $v['price_cents']);
            self::assertLessThanOrEqual(4000000, $v['price_cents']);
        }
        foreach ($this->request('GET', '/api/v1/vehicles?per_page=100&year_min=2024&year_max=2025&mileage_max=30000')['items'] as $v) {
            self::assertContains($v['year'], [2024, 2025]);
            self::assertLessThanOrEqual(30000, $v['mileage']);
        }
        foreach ($this->request('GET', '/api/v1/vehicles?per_page=100&status=hold')['items'] as $v) {
            self::assertSame('hold', $v['status']);
        }
        self::assertSame(['SUV'], array_values(array_unique(array_column($this->request('GET', '/api/v1/vehicles?per_page=100&body=SUV')['items'], 'body'))));
    }

    public function testSearchMatchesStockVinMakeModelAndYearWordsTogether(): void
    {
        $this->signInAs('sales');

        self::assertSame(['MM-1001'], $this->stocks('q=mm-1001'));
        self::assertSame(['MM-1001'], $this->stocks('q=1MUST1001'), 'A VIN prefix matches.');
        $mustangs = $this->request('GET', '/api/v1/vehicles?per_page=100&q=ford mustang')['items'];
        self::assertNotEmpty($mustangs);
        self::assertSame(['Mustang'], array_values(array_unique(array_column($mustangs, 'model'))), 'Every word has to match.');
        self::assertSame([], $this->stocks('q=%25'), '"%" is a literal, not a wildcard.');
    }

    public function testSortingPutsUnpricedVehiclesLastEitherWay(): void
    {
        $this->signInAs('sales');
        foreach (['price_asc', 'price_desc'] as $sort) {
            $items = $this->request('GET', '/api/v1/vehicles?per_page=100&sort='.$sort)['items'];
            $prices = array_column($items, 'price_cents');
            $priced = array_values(array_filter($prices, static fn ($p): bool => null !== $p));
            self::assertSame(\array_slice($prices, 0, \count($priced)), $priced, $sort.': priced vehicles come first.');
            $expected = $priced;
            'price_asc' === $sort ? sort($expected) : rsort($expected);
            self::assertSame($expected, $priced, $sort.' orders by price.');
        }
        $years = array_column($this->request('GET', '/api/v1/vehicles?per_page=100&sort=year_desc')['items'], 'year');
        $sorted = $years;
        rsort($sorted);
        self::assertSame($sorted, $years);
        $miles = array_column($this->request('GET', '/api/v1/vehicles?per_page=100&sort=mileage_asc')['items'], 'mileage');
        $asc = $miles;
        sort($asc);
        self::assertSame($asc, $miles);
    }

    public function testFacetsCountWhatTheFiltersOffer(): void
    {
        $this->signInAs('sales');
        $facets = $this->request('GET', '/api/v1/vehicles/facets');

        $this->assertStatus(200);
        $makes = array_column($facets['makes'], 'count', 'value');
        self::assertSame(40, array_sum($makes), 'Every vehicle has a make, so the counts add up to the inventory.');
        self::assertSame($makes['Tesla'], $this->request('GET', '/api/v1/vehicles?make=Tesla')['total'], 'A facet count is what the filter then returns.');
        self::assertArrayHasKey('Truck', array_column($facets['bodies'], 'count', 'value'));
        self::assertNotNull($facets['year_min']);
        self::assertGreaterThan($facets['price_min_cents'], $facets['price_max_cents']);
    }

    public function testTheListCarriesVehiclesWithoutAPrice(): void
    {
        $this->signInAs('sales');
        $unpriced = array_filter($this->request('GET', '/api/v1/vehicles?per_page=100')['items'], static fn (array $v): bool => null === $v['price_cents']);

        self::assertCount(5, $unpriced, 'Five seeded vehicles are "call for price".');
    }

    public function testSignedOutVisitorsAreRefused(): void
    {
        $this->request('GET', '/api/v1/vehicles');
        $this->assertStatus(401);
    }

    /** @return array<string, mixed> */
    private function decoded(): array
    {
        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
