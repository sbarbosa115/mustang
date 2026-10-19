<?php

namespace App\Tests\Unit\Inventory;

use App\Inventory\Application\Import\VehicleRow;
use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Model\VehicleCondition;
use App\Inventory\Domain\Model\VehicleStatus;
use App\Shared\Domain\Error\InvalidValue;
use PHPUnit\Framework\TestCase;

final class VehicleTest extends TestCase
{
    private function vehicle(): Vehicle
    {
        return new Vehicle('d1', 'S-1', 2024, 'Ford', 'F-150', VehicleCondition::Used, trim: 'XLT', priceCents: 4000000);
    }

    public function testReviseLeavesNullFieldsAndClearsEmptyTexts(): void
    {
        $v = $this->vehicle();
        $v->revise(priceCents: 3900000, trim: '');

        self::assertSame(3900000, $v->priceCents());
        self::assertNull($v->trim(), 'An empty string clears an optional text.');
        self::assertSame('Ford', $v->make(), 'A field left out is unchanged.');
        self::assertSame('S-1', $v->stockNumber());
    }

    public function testStatusChangeDoesNotTouchTheListing(): void
    {
        $v = $this->vehicle();
        $v->changeStatus(VehicleStatus::Sold);

        self::assertSame(VehicleStatus::Sold, $v->status());
        self::assertSame(4000000, $v->priceCents());
    }

    public function testACsvRowParsesDollarsAndConditionAliases(): void
    {
        $row = VehicleRow::fromArray(['stock_number' => ' A1 ', 'year' => '2023', 'make' => 'Kia', 'model' => 'Soul', 'condition' => 'Certified', 'price' => '$24,999.90', 'mileage' => '12,345', 'status' => 'in transit']);

        self::assertSame('A1', $row->stockNumber);
        self::assertSame(VehicleCondition::Cpo, $row->condition);
        self::assertSame(2499990, $row->priceCents);
        self::assertSame(12345, $row->mileage);
        self::assertSame(VehicleStatus::InTransit, $row->status);
    }

    public function testACsvRowNamesTheFieldThatIsWrong(): void
    {
        try {
            VehicleRow::fromArray(['stock_number' => 'A1', 'year' => '2023', 'make' => 'Kia', 'model' => 'Soul', 'condition' => 'new', 'mileage' => '-4']);
            self::fail('A negative mileage is refused.');
        } catch (InvalidValue $e) {
            self::assertSame('mileage', $e->detail()['field']);
        }
    }
}
