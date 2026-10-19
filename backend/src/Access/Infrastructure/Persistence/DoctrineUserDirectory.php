<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Application\Query\UserQueries;
use App\Shared\Application\Port\UserDirectory;

final readonly class DoctrineUserDirectory implements UserDirectory
{
    public function __construct(private UserQueries $users)
    {
    }

    public function names(array $userIds): array
    {
        return $this->users->names(array_values(array_filter($userIds, static fn (?string $id): bool => null !== $id)));
    }
}
