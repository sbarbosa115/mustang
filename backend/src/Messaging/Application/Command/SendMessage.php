<?php

namespace App\Messaging\Application\Command;

use App\Shared\Domain\Channel;

final readonly class SendMessage
{
    public function __construct(
        public string $conversationId,
        public Channel $channel,
        public string $body,
        public ?string $subject,
        public ?string $templateId,
        public ?string $senderUserId,
    ) {
    }
}
