<?php

namespace App\Inventory\UI\Http\Output;

use App\Shared\UI\Http\Output\RowErrorOutput;

final readonly class VehicleImportOutput
{
    /**
     * @param list<RowErrorOutput> $errors
     */
    public function __construct(
        public string $id,
        public string $fileName,
        public \App\Inventory\Domain\Model\VehicleImportStatus $status,
        public int $rowsTotal,
        public int $rowsOk,
        public int $rowsFailed,
        public array $errors,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
