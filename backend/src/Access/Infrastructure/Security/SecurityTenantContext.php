<?php

namespace App\Access\Infrastructure\Security;

use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotAllowed;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The tenant is the signed-in user's dealership. Console commands and seeders set one explicitly with actAs().
 */
final class SecurityTenantContext implements TenantContext
{
    private ?string $dealershipOverride = null;
    private ?string $userOverride = null;

    public function __construct(private readonly Security $security)
    {
    }

    public function actAs(string $dealershipId, ?string $userId = null): void
    {
        $this->dealershipOverride = $dealershipId;
        $this->userOverride = $userId;
    }

    public function reset(): void
    {
        $this->dealershipOverride = null;
        $this->userOverride = null;
    }

    public function dealershipId(): string
    {
        if (null !== $this->dealershipOverride) {
            return $this->dealershipOverride;
        }
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new NotAllowed('not_signed_in', 'Sign in first.');
        }

        return $user->dealershipId;
    }

    public function userId(): ?string
    {
        if (null !== $this->dealershipOverride) {
            return $this->userOverride;
        }
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? $user->id : null;
    }

    public function hasRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }
}
