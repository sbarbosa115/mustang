<?php

namespace App\Access\Infrastructure\Totp;

use App\Access\Application\Port\MfaAttempts;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Five codes per user in five minutes (config/packages/mfa.yaml), cleared by a correct one.
 */
final readonly class RateLimitedMfaAttempts implements MfaAttempts
{
    public function __construct(#[Autowire(service: 'limiter.mfa_verify')] private RateLimiterFactoryInterface $limiter)
    {
    }

    public function attempt(string $userId): bool
    {
        return $this->limiter->create($userId)->consume()->isAccepted();
    }

    public function clear(string $userId): void
    {
        $this->limiter->create($userId)->reset();
    }
}
