<?php

namespace App\Inventory\UI\Cli;

use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Model\VehicleCondition;
use App\Inventory\Domain\Model\VehicleStatus;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 40 vehicles for Mustang Motors Tampa (stock numbers MM-1001 to MM-1040: new, used and certified, six makes, five
 * without a price, a few on hold, sold or in transit) and 3 for Other Motors (OM-2001 to OM-2003). Deterministic, so
 * tests and the smoke suite can name them. Keys: vehicle.MM-1001 … and vehicle.OM-2001 ….
 */
final readonly class VehicleSeeder implements DemoSeeder
{
    /** make, model, trim, body, drivetrain, fuel, base price in dollars */
    private const array MODELS = [
        ['Ford', 'F-150', 'XLT', 'Truck', '4WD', 'Gas', 48900],
        ['Ford', 'Mustang', 'GT Premium', 'Coupe', 'RWD', 'Gas', 44500],
        ['Ford', 'Explorer', 'Limited', 'SUV', 'AWD', 'Gas', 51200],
        ['Toyota', 'Camry', 'SE', 'Sedan', 'FWD', 'Gas', 29800],
        ['Toyota', 'RAV4', 'XLE', 'SUV', 'AWD', 'Hybrid', 36400],
        ['Honda', 'Civic', 'Sport', 'Sedan', 'FWD', 'Gas', 26500],
        ['Honda', 'CR-V', 'EX-L', 'SUV', 'AWD', 'Gas', 35800],
        ['Chevrolet', 'Silverado 1500', 'LT', 'Truck', '4WD', 'Gas', 47300],
        ['Chevrolet', 'Equinox', 'RS', 'SUV', 'AWD', 'Gas', 33100],
        ['Nissan', 'Rogue', 'SV', 'SUV', 'AWD', 'Gas', 31900],
        ['Tesla', 'Model 3', 'Long Range', 'Sedan', 'AWD', 'Electric', 42600],
        ['Jeep', 'Wrangler', 'Sahara', 'SUV', '4WD', 'Gas', 49800],
    ];
    private const array COLORS = ['White', 'Black', 'Silver', 'Blue', 'Red', 'Gray'];
    private const array INTERIORS = ['Black', 'Gray', 'Tan'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 800;
    }

    public function seed(SeedContext $context): void
    {
        $mustang = $context->id('dealership.mustang');
        for ($i = 1; $i <= 40; ++$i) {
            [$make, $model, $trim, $body, $drive, $fuel, $base] = self::MODELS[($i - 1) % \count(self::MODELS)];
            $condition = match (true) {
                $i % 20 <= 7 && $i % 20 > 0 => VehicleCondition::New,
                0 === $i % 7 || 0 === $i % 20 => VehicleCondition::Cpo,
                default => VehicleCondition::Used,
            };
            $year = match ($condition) {
                VehicleCondition::New => 2026,
                VehicleCondition::Cpo => 2023 + $i % 2,
                VehicleCondition::Used => 2016 + $i % 8,
            };
            $mileage = match ($condition) {
                VehicleCondition::New => 5 + $i,
                VehicleCondition::Cpo => 14000 + $i * 450,
                VehicleCondition::Used => 28000 + $i * 1900,
            };
            $price = match ($condition) {
                VehicleCondition::New => $base,
                VehicleCondition::Cpo => (int) ($base * 0.82),
                VehicleCondition::Used => (int) ($base * (0.78 - 0.04 * ((2026 - $year) / 2))),
            };
            $status = match (true) {
                \in_array($i, [9, 23, 31], true) => VehicleStatus::Hold,
                \in_array($i, [14, 27, 36], true) => VehicleStatus::Sold,
                \in_array($i, [5, 38], true) => VehicleStatus::InTransit,
                default => VehicleStatus::Available,
            };
            $stock = \sprintf('MM-%d', 1000 + $i);
            $vehicle = new Vehicle(
                $mustang,
                $stock,
                $year,
                $make,
                $model,
                $condition,
                $status,
                vin: \sprintf('1MUST%04d%08d', 1000 + $i, $i),
                trim: $trim,
                body: $body,
                drivetrain: $drive,
                fuel: $fuel,
                exteriorColor: self::COLORS[$i % \count(self::COLORS)],
                interiorColor: self::INTERIORS[$i % \count(self::INTERIORS)],
                mileage: $mileage,
                // Five vehicles are listed without a price ("Call for price").
                priceCents: \in_array($i, [4, 12, 19, 28, 33], true) ? null : $price * 100,
                description: \sprintf('%d %s %s %s, stock %s.', $year, $make, $model, $trim, $stock),
            );
            $this->em->persist($vehicle);
            $context->set('vehicle.'.$stock, $vehicle->id());
        }

        $other = $context->id('dealership.other');
        foreach ([[2024, 'Subaru', 'Outback', VehicleCondition::Used, 31900], [2026, 'Kia', 'Telluride', VehicleCondition::New, 46800], [2022, 'Mazda', 'CX-5', VehicleCondition::Cpo, 27900]] as $n => [$year, $make, $model, $condition, $price]) {
            $stock = \sprintf('OM-%d', 2001 + $n);
            $vehicle = new Vehicle($other, $stock, $year, $make, $model, $condition, mileage: 12000 * $n, priceCents: $price * 100);
            $this->em->persist($vehicle);
            $context->set('vehicle.'.$stock, $vehicle->id());
        }
    }
}
