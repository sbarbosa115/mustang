<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether sign-in asks for the TOTP code. Always in production (NFR-02); a developer may switch it off on their own
 * machine with MUSTANG_MFA_REQUIRED=0 in backend/.env.local. The test suites and the smoke suite keep it on.
 */
class MfaPolicy
{
    public function __construct(
        #[Autowire(env: 'bool:MUSTANG_MFA_REQUIRED')] private readonly bool $required,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
    }

    public function isRequired(): bool
    {
        return $this->required || 'prod' === $this->environment;
    }
}
