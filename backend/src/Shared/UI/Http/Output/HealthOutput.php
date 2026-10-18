<?php

namespace App\Shared\UI\Http\Output;

final readonly class HealthOutput
{
    public function __construct(
        public string $status,
        public string $environment,
        public string $smsTransport,
    ) {
    }
}
