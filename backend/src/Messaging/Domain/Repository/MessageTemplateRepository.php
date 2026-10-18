<?php

namespace App\Messaging\Domain\Repository;

use App\Messaging\Domain\Model\MessageTemplate;

interface MessageTemplateRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): MessageTemplate;

    public function add(MessageTemplate $messageTemplate): void;

    public function remove(MessageTemplate $messageTemplate): void;
}
