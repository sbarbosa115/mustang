<?php

namespace App\Messaging\Application\Query;

use App\Messaging\Domain\Model\MessageTemplate;
use App\Shared\Domain\Channel;

/**
 * Reads of the current dealership's message templates.
 */
interface TemplateQueries
{
    /**
     * By name, case-insensitive.
     *
     * @return list<MessageTemplate>
     */
    public function list(?Channel $channel = null, ?bool $active = null): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's template
     */
    public function byId(string $id): MessageTemplate;
}
