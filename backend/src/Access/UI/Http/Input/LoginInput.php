<?php

namespace App\Access\UI\Http\Input;

/**
 * Read by the firewall's json_login, not by a controller; it exists to document the request.
 */
final class LoginInput
{
    public function __construct(
        public string $email = '',
        public string $password = '',
    ) {
    }
}
