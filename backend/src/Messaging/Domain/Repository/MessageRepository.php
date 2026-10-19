<?php

namespace App\Messaging\Domain\Repository;

use App\Messaging\Domain\Model\Message;

interface MessageRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Message;

    public function add(Message $message): void;

    /**
     * For work without a signed-in user (the mail worker): the dealership is named explicitly.
     *
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function getInDealership(string $id, string $dealershipId): Message;
}
