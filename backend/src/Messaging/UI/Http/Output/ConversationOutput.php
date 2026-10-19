<?php

namespace App\Messaging\UI\Http\Output;

use App\Shared\UI\Http\Output\ChannelConsentOutput;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class ConversationOutput
{
    public function __construct(
        public string $id,
        public CustomerRefOutput $customer,
        public int $unreadCount,
        public bool $unreplied,
        public bool $fumbled,
        public ChannelConsentOutput $consent,
        public ?UserRefOutput $assignee = null,
        public ?MessageOutput $lastMessage = null,
        public ?\DateTimeImmutable $lastMessageAt = null,
    ) {
    }
}
