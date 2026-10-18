<?php

namespace App\DataImport\Application\EventHandler;

use App\DataImport\Application\Command\ProcessImport;
use App\DataImport\Application\Command\RunImport;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * The worker receives the queued message on the event bus, which has no transaction: the work itself is a command.
 */
final readonly class RunImportHandler implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(RunImport $message): void
    {
        $this->commands->dispatch(new ProcessImport($message->importJobId));
    }
}
