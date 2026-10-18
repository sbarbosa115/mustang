<?php

namespace App\Reporting\Application\Query;

interface KpiQueries
{
    /**
     * The dealership's IANA timezone; day boundaries of every period are drawn in it.
     */
    public function timezone(string $dealershipId): string;

    /**
     * The KPIs of one dealership for the instants from $start (inclusive) to $end (exclusive), both UTC.
     *
     * Leads and appointments are followed as a cohort: the funnel counts the leads received in the period that went on
     * to be contacted, to get an appointment, to show and to buy, so every step is part of the one before and no rate
     * passes 100%.
     */
    public function snapshot(string $dealershipId, \DateTimeImmutable $start, \DateTimeImmutable $end): KpiSnapshot;
}
