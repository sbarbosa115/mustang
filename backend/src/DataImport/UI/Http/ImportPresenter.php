<?php

namespace App\DataImport\UI\Http;

use App\DataImport\Domain\Model\ImportJob;
use App\DataImport\UI\Http\Output\ImportJobOutput;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\RowErrorOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds the import responses, with the uploader's name.
 */
final readonly class ImportPresenter
{
    public function __construct(private UserDirectory $users)
    {
    }

    public function job(ImportJob $job): ImportJobOutput
    {
        return $this->jobs([$job])[0];
    }

    /**
     * @param list<ImportJob> $jobs
     *
     * @return list<ImportJobOutput>
     */
    public function jobs(array $jobs): array
    {
        $names = $this->users->names(array_map(static fn (ImportJob $j): string => $j->uploadedByUserId(), $jobs));

        return array_map(static function (ImportJob $job) use ($names): ImportJobOutput {
            $errors = [];
            foreach ($job->errorsJson() as $error) {
                if (\is_array($error)) {
                    $errors[] = new RowErrorOutput((int) ($error['row'] ?? 0), (string) ($error['message'] ?? ''), isset($error['field']) ? (string) $error['field'] : null);
                }
            }
            $uploader = $job->uploadedByUserId();

            return new ImportJobOutput(
                $job->id(),
                $job->type(),
                $job->fileName(),
                $job->status(),
                $job->rowsTotal(),
                $job->rowsCreated(),
                $job->rowsUpdated(),
                $job->rowsSkipped(),
                $job->rowsFailed(),
                $errors,
                $job->createdAt(),
                new UserRefOutput($uploader, $names[$uploader] ?? ''),
                $job->finishedAt(),
            );
        }, $jobs);
    }
}
