<?php

namespace App\Worklist\Application\Query;

/**
 * What the manager's queue reads beyond the customers' facts (WorklistQueries).
 */
interface InterventionQueries
{
    /**
     * The gross a deal without its own expected gross puts at risk ($3,000 unless the dealership set it).
     */
    public function averageGrossCents(): int;
}
