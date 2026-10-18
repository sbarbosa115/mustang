<?php

namespace App\Deal\Application\Command;

use App\Deal\Application\DealChecks;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class AttachVehicleHandler implements CommandHandler
{
    public function __construct(private DealRepository $deals, private DealChecks $checks)
    {
    }

    public function __invoke(AttachVehicle $command): void
    {
        $deal = $this->deals->get($command->dealId);
        $this->checks->vehicle($deal->dealershipId(), $command->vehicleId);
        $deal->attachVehicle($command->vehicleId, $command->vehicleText);
    }
}
