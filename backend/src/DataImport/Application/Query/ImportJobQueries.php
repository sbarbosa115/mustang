<?php

namespace App\DataImport\Application\Query;

use App\DataImport\Domain\Model\ImportJob;

/**
 * Reads of the current dealership's imports.
 */
interface ImportJobQueries
{
    /**
     * Newest first.
     *
     * @return array{items: list<ImportJob>, total: int}
     */
    public function page(int $offset, int $limit): array;
}
