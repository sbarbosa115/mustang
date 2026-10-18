<?php

namespace App\DataImport\Application\Command;

use App\DataImport\Application\Import\CustomerRowImporter;
use App\DataImport\Application\Import\LeadRowImporter;
use App\DataImport\Application\Import\RowOutcome;
use App\DataImport\Application\Port\ActingUser;
use App\DataImport\Application\Port\CsvFile;
use App\DataImport\Application\Port\CustomerCsvReader;
use App\DataImport\Application\Port\LeadCsvReader;
use App\DataImport\Application\Port\TeamMembers;
use App\DataImport\Domain\Event\ImportFinished;
use App\DataImport\Domain\Model\ImportJob;
use App\DataImport\Domain\Model\ImportType;
use App\DataImport\Domain\Repository\ImportJobRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotAllowed;

/**
 * Reads a stored customers or leads file and imports it row by row, as its uploader (the worker has nobody signed in).
 * A bad row is reported with its line number and skipped before anything of it is written; the rest of the file still
 * lands. Duplicates (by phone, then email) are updated, never created twice.
 */
final readonly class ProcessImportHandler implements CommandHandler
{
    private const int MAX_REPORTED_ERRORS = 200;

    public function __construct(
        private ImportJobRepository $jobs,
        private CustomerCsvReader $customerFiles,
        private LeadCsvReader $leadFiles,
        private CustomerRowImporter $customers,
        private LeadRowImporter $leads,
        private TeamMembers $team,
        private ActingUser $actingUser,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ProcessImport $command): void
    {
        $job = $this->jobs->getForWorker($command->importJobId);
        if ($job->isFinished()) {
            return; // A retried message after the work was committed.
        }
        $job->begin();

        try {
            $file = ImportType::Customers === $job->type()
                ? $this->customerFiles->read($job->filePath())
                : $this->leadFiles->read($job->filePath());
            $counts = $this->actingUser->runAs($job->dealershipId(), $job->uploadedByUserId(), fn (): array => $this->rows($job, $file));
        } catch (InvalidValue|NotAllowed $e) {
            $field = $e->detail()['field'] ?? null;
            $job->fail([['row' => 1, 'message' => $e->getMessage(), 'field' => \is_string($field) ? $field : null]], $this->clock->now());
            $this->finished($job);

            return;
        }

        $job->finish($counts['total'], $counts['created'], $counts['updated'], $counts['skipped'], $counts['failed'], $counts['errors'], $this->clock->now());
        $this->finished($job);
    }

    /**
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, errors: list<array{row: int, message: string, field: ?string}>}
     */
    private function rows(ImportJob $job, CsvFile $file): array
    {
        $counts = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $timezone = new \DateTimeZone($this->team->timezoneOf($job->dealershipId()));
        foreach ($file->rows as $line => $values) {
            ++$counts['total'];
            try {
                $outcome = ImportType::Customers === $job->type()
                    ? $this->customers->import($job, $values)
                    : $this->leads->import($job, $values, $timezone);
                match ($outcome) {
                    RowOutcome::Created => ++$counts['created'],
                    RowOutcome::Updated => ++$counts['updated'],
                    RowOutcome::Skipped => ++$counts['skipped'],
                };
            } catch (DomainError $e) {
                ++$counts['failed'];
                if (\count($counts['errors']) < self::MAX_REPORTED_ERRORS) {
                    $field = $e->detail()['field'] ?? null;
                    $counts['errors'][] = ['row' => $line, 'message' => $e->getMessage(), 'field' => \is_string($field) && '' !== $field ? $field : null];
                }
            }
        }

        return $counts;
    }

    private function finished(ImportJob $job): void
    {
        $this->events->publish(new ImportFinished(
            $job->id(),
            $job->dealershipId(),
            $job->type()->value,
            $job->rowsCreated(),
            $job->rowsUpdated(),
            $job->rowsFailed(),
            $this->clock->now(),
        ));
    }
}
