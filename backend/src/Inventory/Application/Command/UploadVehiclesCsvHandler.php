<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Model\VehicleImport;
use App\Inventory\Domain\Repository\VehicleImportRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotAllowed;

final readonly class UploadVehiclesCsvHandler implements CommandHandler
{
    public function __construct(private VehicleImportRepository $imports, private TenantContext $tenant, private EventBus $events)
    {
    }

    public function __invoke(UploadVehiclesCsv $command): string
    {
        $userId = $this->tenant->userId() ?? throw new NotAllowed('not_signed_in', 'Sign in to upload vehicles.');
        $import = new VehicleImport($this->tenant->dealershipId(), $userId, $command->fileName, $command->filePath);
        $this->imports->add($import);
        // Queued once this transaction has committed, so the worker never looks for a record that is not there yet.
        $this->events->publish(new ImportVehiclesCsv($import->id()));

        return $import->id();
    }
}
