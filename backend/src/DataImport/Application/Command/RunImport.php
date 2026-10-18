<?php

namespace App\DataImport\Application\Command;

use App\Shared\Application\Bus\AsyncMessage;

/**
 * Queued when an upload is recorded: on the worker, RunImportHandler turns it into a ProcessImport command.
 */
final readonly class RunImport implements AsyncMessage
{
    public function __construct(public string $importJobId)
    {
    }
}
