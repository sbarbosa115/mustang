<?php

namespace App\Shared\UI\Cli;

/**
 * What earlier seeders created, by key, for later ones to use (e.g. "dealership.mustang", "user.sales@mustang.test").
 */
final class SeedContext
{
    /** @var array<string, string> */
    private array $ids = [];

    public function __construct(public readonly \DateTimeImmutable $now)
    {
    }

    public function set(string $key, string $id): void
    {
        $this->ids[$key] = $id;
    }

    public function id(string $key): string
    {
        return $this->ids[$key] ?? throw new \LogicException(\sprintf('No seeded record "%s"; check seeder priorities.', $key));
    }

    public function has(string $key): bool
    {
        return isset($this->ids[$key]);
    }
}
