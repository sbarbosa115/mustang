<?php

namespace App\Messaging\Domain\Repository;

use App\Messaging\Domain\Model\Conversation;

interface ConversationRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Conversation;

    public function add(Conversation $conversation): void;

    /**
     * The customer's conversation in that dealership (one per customer), or null.
     */
    public function findByCustomer(string $dealershipId, string $customerId): ?Conversation;

    /**
     * For work without a signed-in user (the mail worker): the dealership is named explicitly.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getInDealership(string $id, string $dealershipId): Conversation;
}
