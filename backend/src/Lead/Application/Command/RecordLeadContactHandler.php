<?php

namespace App\Lead\Application\Command;

use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class RecordLeadContactHandler implements CommandHandler
{
    public function __construct(private LeadRepository $leads)
    {
    }

    public function __invoke(RecordLeadContact $command): void
    {
        foreach ($this->leads->openForCustomer($command->dealershipId, $command->customerId) as $lead) {
            $lead->markContacted($command->at);
        }
    }
}
