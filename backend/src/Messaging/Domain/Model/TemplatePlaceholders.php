<?php

namespace App\Messaging\Domain\Model;

/**
 * The placeholders a template may use. The UI fills them (entities/template/lib/render.ts keeps the same list).
 */
final class TemplatePlaceholders
{
    public const array ALL = ['first_name', 'last_name', 'rep_name', 'dealership', 'vehicle', 'appointment_time'];

    /**
     * @return list<string> the names in the text that are not known, each once, in order of appearance
     */
    public static function unknownIn(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);

        return array_values(array_unique(array_filter($matches[1], static fn (string $name): bool => !\in_array($name, self::ALL, true))));
    }
}
