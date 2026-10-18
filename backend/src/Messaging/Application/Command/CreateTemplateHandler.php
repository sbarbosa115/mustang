<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Domain\Model\MessageTemplate;
use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotAllowed;

final readonly class CreateTemplateHandler implements CommandHandler
{
    public function __construct(private MessageTemplateRepository $templates, private TenantContext $tenant)
    {
    }

    public function __invoke(CreateTemplate $command): string
    {
        $template = MessageTemplate::compose(
            $this->tenant->dealershipId(),
            $command->name,
            $command->channel,
            $command->body,
            $this->tenant->userId() ?? throw new NotAllowed('not_signed_in', 'Sign in first.'),
            $command->subject,
            $command->active,
        );
        $this->templates->add($template);

        return $template->id();
    }
}
