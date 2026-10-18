<?php

namespace App\DataImport\Infrastructure\Adapter;

use App\DataImport\Application\Port\ActingUser;
use App\Shared\Domain\Error\NotAllowed;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Signs the uploader in on the worker for the length of the work, through the firewall's own user provider, so every
 * other context reads the dealership from TenantContext as it does in a request; the previous token (none, on the
 * worker) is put back afterwards.
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

    public function runAs(string $dealershipId, string $userId, callable $work): mixed
    {
        $email = $this->connection->fetchOne(
            'SELECT email FROM users WHERE id = :id AND dealership_id = :dealership AND active = 1',
            ['id' => $userId, 'dealership' => $dealershipId],
        );
        if (!\is_string($email)) {
            throw new NotAllowed('uploader_unavailable', 'The person who uploaded the file is no longer an active user.');
        }
        try {
            $user = $this->users->loadUserByIdentifier($email);
        } catch (UserNotFoundException) {
            throw new NotAllowed('uploader_unavailable', 'The person who uploaded the file is no longer an active user.');
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
