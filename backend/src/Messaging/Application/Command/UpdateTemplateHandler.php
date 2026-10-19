<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class UpdateTemplateHandler implements CommandHandler
{
    public function __construct(private MessageTemplateRepository $templates)
    {
    }

    public function __invoke(UpdateTemplate $command): void
    {
        $this->templates->get($command->templateId)->rewrite($command->name, $command->body, $command->subject, $command->active);
    }
}
