<?php

namespace App\Messaging\Application\Command;

final readonly class DeleteTemplate
{
    public function __construct(public string $templateId)
    {
    }
}
