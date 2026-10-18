<?php

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Identifiers are UUID v7 strings: sortable by creation time, safe to show in URLs.
 */
final class Id
{
    public static function new(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    public static function isValid(string $id): bool
    {
        return Uuid::isValid($id);
    }
}
