<?php

namespace App\Shared\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * Whether each channel may be used for a customer right now (FR-11, FR-12).
 */
final readonly class ChannelConsentOutput
{
    public function __construct(
        #[OA\Property(enum: ['granted', 'revoked', 'missing', 'opted_out'])]
        public string $text,
        #[OA\Property(enum: ['granted', 'revoked', 'missing', 'opted_out'])]
        public string $email,
        #[OA\Property(enum: ['granted', 'revoked', 'missing', 'opted_out'])]
        public string $call,
    ) {
    }
}
