<?php

namespace App\Shared\Application\Bus;

/**
 * Writes go through here: the handler runs in one transaction and returns an id or a small result.
 */
interface CommandBus
{
    public function dispatch(object $command): mixed;
}
