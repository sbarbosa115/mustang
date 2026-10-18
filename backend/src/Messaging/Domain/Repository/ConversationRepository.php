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
}
