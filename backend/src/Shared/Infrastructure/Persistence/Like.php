<?php

namespace App\Shared\Infrastructure\Persistence;

/**
 * Builds a LIKE pattern from what a user typed, so "%" and "_" match themselves.
 */
final class Like
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    public static function startsWith(string $term): string
    {
        return addcslashes($term, '%_\\').'%';
    }
}
