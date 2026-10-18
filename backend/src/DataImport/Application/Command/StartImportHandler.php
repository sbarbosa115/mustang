<?php

namespace App\DataImport\Application\Command;

use App\DataImport\Domain\Model\ImportJob;
use App\DataImport\Domain\Repository\ImportJobRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotAllowed;

final readonly class StartImportHandler implements CommandHandler
{
    public function __construct(private ImportJobRepository $jobs, private TenantContext $tenant, private EventBus $events)
    {
    }

    public function __invoke(StartImport $command): string
    {
        $userId = $this->tenant->userId() ?? throw new NotAllowed('not_signed_in', 'Sign in to import a file.');
        $job = new ImportJob($this->tenant->dealershipId(), $command->type, $userId, $command->fileName, $command->filePath);
        $this->jobs->add($job);
        // Queued once this transaction has committed, so the worker never looks for a job that is not there yet.
        $this->events->publish(new RunImport($job->id()));

        return $job->id();
    }
}
