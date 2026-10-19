<?php

namespace App\Timeline\Application;

/**
 * The key of one timeline entry, kept in `activities.event_id` (64 characters at most): the entry's stable `code`
 * (what the UI words through i18n, e.g. "message_sent_email"), a colon, and a hash of the event class, its record
 * id and the instant it happened. The same event always gives the same key, so a redelivery adds nothing.
 */
final class ActivityKey
{
    public static function of(string $code, string $eventClass, string $recordId, \DateTimeImmutable $occurredAt): string
    {
        return $code.':'.substr(hash('sha1', $eventClass.'|'.$recordId.'|'.$occurredAt->format('Y-m-d\TH:i:s.u')), 0, 32);
    }

    /**
     * The code of a key, or null for an entry that has none.
     */
    public static function codeOf(?string $key): ?string
    {
        if (null === $key || !str_contains($key, ':')) {
            return null;
        }

        return explode(':', $key, 2)[0];
    }
}
