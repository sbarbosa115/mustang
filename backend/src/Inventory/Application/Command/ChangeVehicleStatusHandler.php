<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class ChangeVehicleStatusHandler implements CommandHandler
{
    public function __construct(private VehicleRepository $vehicles)
    {
    }

    public function __invoke(ChangeVehicleStatus $command): void
    {
        $this->vehicles->get($command->id)->changeStatus($command->status);
    }
}
