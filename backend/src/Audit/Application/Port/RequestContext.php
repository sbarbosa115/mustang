<?php

namespace App\Audit\Application\Port;

/**
 * The HTTP request being served, if any: its id (the caller's X-Request-Id, or one made for it) and the client's
 * address. Both are null outside a request (console commands, the worker).
 */
interface RequestContext
{
    public function requestId(): ?string;

    public function ip(): ?string;
}
