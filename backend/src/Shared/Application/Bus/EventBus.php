<?php

namespace App\Shared\Application\Bus;

/**
 * Domain events published from a handler are delivered after the command's transaction commits.
 */
interface EventBus
{
    public function publish(object ...$events): void;
}
