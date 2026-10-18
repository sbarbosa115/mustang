<?php

namespace App\Messaging\Infrastructure\Adapter;

use App\Messaging\Application\Port\ActingUser;
use App\Shared\Domain\Error\NotAllowed;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Signs the dealership's first active admin in for the length of the work, through the firewall's own user provider,
 * so the other contexts read the dealership from TenantContext as they do in a request; the previous token (none, on
 * the console) is put back afterwards.
 */
final readonly class SecurityActingUser implements ActingUser
{
    /**
     * @param UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> $users
     */
    public function __construct(
        private TokenStorageInterface $tokens,
        #[Autowire(service: 'security.user.provider.concrete.mustang_users')]
        private UserProviderInterface $users,
        private Connection $connection,
    ) {
    }

    public function runAsAdminOf(string $dealershipId, callable $work): mixed
    {
        $email = $this->connection->fetchOne(
            "SELECT email FROM users WHERE dealership_id = :dealership AND role = 'admin' AND active = 1 ORDER BY created_at, id LIMIT 1",
            ['dealership' => $dealershipId],
        );
        if (!\is_string($email)) {
            throw new NotAllowed('no_active_admin', 'This dealership has no active admin to act as.');
        }
        try {
            $user = $this->users->loadUserByIdentifier($email);
        } catch (UserNotFoundException) {
            throw new NotAllowed('no_active_admin', 'This dealership has no active admin to act as.');
        }

        $previous = $this->tokens->getToken();
        $this->tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        try {
            return $work();
        } finally {
            $this->tokens->setToken($previous);
        }
    }
}
