<?php

namespace App\Inventory\Application\EventHandler;

use App\Inventory\Application\Command\ImportVehiclesCsv;
use App\Inventory\Application\Command\ProcessVehicleImport;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * The worker receives the queued message on the event bus, which has no transaction: the work itself is a command.
 */
final readonly class ImportVehiclesCsvHandler implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(ImportVehiclesCsv $message): void
    {
        $this->commands->dispatch(new ProcessVehicleImport($message->importId));
    }
}
