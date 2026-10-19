<?php

namespace App\Compliance\Application\Command;

/**
 * A customer texted a keyword (STOP, START…) from a phone. Everything is explicit: it runs after an inbound message,
 * often with nobody signed in (the worker, the console). Answers the keyword applied, or null when the text is none.
 */
final readonly class ApplyKeyword
{
    public function __construct(
        public string $dealershipId,
        public string $customerId,
        public string $phone,
        public string $body,
    ) {
    }
}
