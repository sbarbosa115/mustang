<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Application\Bus\CommandHandler;

/**
 * Sent messages keep the template id they were written from; it simply stops resolving.
 */
final readonly class DeleteTemplateHandler implements CommandHandler
{
    public function __construct(private MessageTemplateRepository $templates)
    {
    }

    public function __invoke(DeleteTemplate $command): void
    {
        $this->templates->remove($this->templates->get($command->templateId));
    }
}
