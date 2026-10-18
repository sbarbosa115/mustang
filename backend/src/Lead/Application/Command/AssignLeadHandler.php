<?php

namespace App\Lead\Application\Command;

use App\Lead\Application\LeadAssigneePolicy;
use App\Lead\Domain\Event\LeadAssigned;
use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

/**
 * Hands a lead to a rep by hand; raises LeadAssigned when the rep changes.
 */
final readonly class AssignLeadHandler implements CommandHandler
{
    public function __construct(
        private LeadRepository $leads,
        private LeadAssigneePolicy $assignees,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AssignLead $command): string
    {
        $lead = $this->leads->get($command->leadId);
        $this->assignees->check($command->userId);
        if ($lead->assignTo($command->userId)) {
            $this->events->publish(new LeadAssigned($lead->id(), $lead->dealershipId(), $lead->customerId(), $command->userId, $this->clock->now()));
        }

        return $lead->id();
    }
}
