<?php

namespace App\Compliance\UI\Http\Output;

use App\Shared\UI\Http\Output\ChannelConsentOutput;

final readonly class ConsentListOutput
{
    /**
     * @param list<ConsentOutput> $items
     */
    public function __construct(
        public array $items,
        public ChannelConsentOutput $summary,
    ) {
    }
}
