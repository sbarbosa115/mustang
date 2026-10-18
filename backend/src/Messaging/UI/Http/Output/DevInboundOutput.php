<?php

namespace App\Messaging\UI\Http\Output;

final readonly class DevInboundOutput
{
    public function __construct(
        public string $customerId,
        public string $conversationId,
        public string $messageId,
    ) {
    }
}
