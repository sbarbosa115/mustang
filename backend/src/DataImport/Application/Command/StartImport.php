<?php

namespace App\DataImport\Application\Command;

use App\DataImport\Domain\Model\ImportType;

/**
 * Records an uploaded file (already stored at $filePath by the UI) as a pending import and queues RunImport so the
 * worker processes it; answers the job's id.
 */
final readonly class StartImport
{
    public function __construct(public ImportType $type, public string $fileName, public string $filePath)
    {
    }
}
