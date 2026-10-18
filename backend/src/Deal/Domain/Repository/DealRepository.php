<?php

namespace App\Deal\Domain\Repository;

use App\Deal\Domain\Model\Deal;

interface DealRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Deal;

    public function add(Deal $deal): void;

    /**
     * For work without a signed-in user (event handlers on the worker or a console command): the dealership is
     * named explicitly.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getInDealership(string $id, string $dealershipId): Deal;

    /**
     * The customer's open deal (neither delivered nor lost) in that dealership, the newest when there are several.
     */
    public function openForCustomer(string $dealershipId, string $customerId): ?Deal;

    /**
     * The deal opened for this lead, if any (an event delivered twice opens one deal).
     */
    public function findByLead(string $dealershipId, string $leadId): ?Deal;
}
