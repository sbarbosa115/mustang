<?php

namespace App\Lead\Application\Command;

use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class CloseLeadHandler implements CommandHandler
{
    public function __construct(private LeadRepository $leads)
    {
    }

    public function __invoke(CloseLead $command): string
    {
        $lead = $this->leads->get($command->leadId);
        $lead->close();

        return $lead->id();
    }
}
