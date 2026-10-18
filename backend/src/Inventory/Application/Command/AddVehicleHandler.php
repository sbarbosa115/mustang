<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Error\StockNumberTaken;
use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;

final readonly class AddVehicleHandler implements CommandHandler
{
    public function __construct(private VehicleRepository $vehicles, private TenantContext $tenant)
    {
    }

    public function __invoke(AddVehicle $command): string
    {
        $dealership = $this->tenant->dealershipId();
        $stockNumber = trim($command->stockNumber);
        if (null !== $this->vehicles->findByStockNumber($dealership, $stockNumber)) {
            throw new StockNumberTaken($stockNumber);
        }
        $vehicle = new Vehicle(
            $dealership,
            $stockNumber,
            $command->year,
            trim($command->make),
            trim($command->model),
            $command->condition,
            vin: self::blank($command->vin),
            trim: self::blank($command->trim),
            body: self::blank($command->body),
            drivetrain: self::blank($command->drivetrain),
            fuel: self::blank($command->fuel),
            exteriorColor: self::blank($command->exteriorColor),
            interiorColor: self::blank($command->interiorColor),
            mileage: $command->mileage,
            priceCents: $command->priceCents,
            photoUrl: self::blank($command->photoUrl),
            description: self::blank($command->description),
        );
        $this->vehicles->add($vehicle);

        return $vehicle->id();
    }

    private static function blank(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
