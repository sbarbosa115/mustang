<?php

namespace App\DataImport\Domain\Repository;

use App\DataImport\Domain\Model\ImportJob;

interface ImportJobRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): ImportJob;

    public function add(ImportJob $importJob): void;

    /**
     * For the worker, where nobody is signed in: the job carries its dealership.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getForWorker(string $id): ImportJob;
}
