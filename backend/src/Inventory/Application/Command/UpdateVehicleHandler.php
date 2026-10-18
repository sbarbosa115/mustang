<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class UpdateVehicleHandler implements CommandHandler
{
    public function __construct(private VehicleRepository $vehicles)
    {
    }

    public function __invoke(UpdateVehicle $command): void
    {
        $this->vehicles->get($command->id)->revise(
            year: $command->year,
            make: null === $command->make ? null : trim($command->make),
            model: null === $command->model ? null : trim($command->model),
            condition: $command->condition,
            vin: $command->vin,
            trim: $command->trim,
            body: $command->body,
            drivetrain: $command->drivetrain,
            fuel: $command->fuel,
            exteriorColor: $command->exteriorColor,
            interiorColor: $command->interiorColor,
            mileage: $command->mileage,
            priceCents: $command->priceCents,
            photoUrl: $command->photoUrl,
            description: $command->description,
        );
    }
}
