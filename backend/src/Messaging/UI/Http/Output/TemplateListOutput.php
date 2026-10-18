<?php

namespace App\Messaging\UI\Http\Output;

final readonly class TemplateListOutput
{
    /**
     * @param list<TemplateOutput> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
