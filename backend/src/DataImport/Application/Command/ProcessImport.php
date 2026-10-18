<?php

namespace App\DataImport\Application\Command;

/**
 * Processes a pending customers or leads import, in one transaction.
 */
final readonly class ProcessImport
{
    public function __construct(public string $importJobId)
    {
    }
}
