<?php

namespace App\DataImport\Domain\Event;

final readonly class ImportFinished
{
    public function __construct(
        public string $importJobId,
        public string $dealershipId,
        public string $type,
        public int $rowsCreated,
        public int $rowsUpdated,
        public int $rowsFailed,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
