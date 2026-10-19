<?php

namespace App\DataImport\UI\Http\Output;

use App\Shared\UI\Http\Output\RowErrorOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class ImportJobOutput
{
    /**
     * @param list<RowErrorOutput> $errors
     */
    public function __construct(
        public string $id,
        public \App\DataImport\Domain\Model\ImportType $type,
        public string $fileName,
        public \App\DataImport\Domain\Model\ImportStatus $status,
        public int $rowsTotal,
        public int $rowsCreated,
        public int $rowsUpdated,
        public int $rowsSkipped,
        public int $rowsFailed,
        public array $errors,
        public \DateTimeImmutable $createdAt,
        public ?UserRefOutput $uploadedBy = null,
        public ?\DateTimeImmutable $finishedAt = null,
    ) {
    }
}
