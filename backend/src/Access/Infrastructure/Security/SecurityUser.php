<?php

namespace App\Access\Infrastructure\Security;

use App\Access\Domain\Model\User;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The signed-in user as the firewall sees it. Kept apart from the User entity so the Domain names no framework class.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $dealershipId,
        public string $email,
        private string $passwordHash,
        private array $roles,
        public bool $active,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->id(),
            $user->dealershipId(),
            $user->email(),
            $user->passwordHash(),
            [$user->role()->securityRole()],
            $user->active(),
        );
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        // A role change, a password change or a deactivation ends the session on the next request.
        return $user instanceof self
            && $user->id === $this->id
            && $user->passwordHash === $this->passwordHash
            && $user->roles === $this->roles
            && $user->active === $this->active;
    }
}
