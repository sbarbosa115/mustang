<?php

namespace App\Lead\UI\Http;

use App\Lead\Domain\Model\Lead;
use App\Lead\UI\Http\Output\LeadOutput;
use App\Shared\Application\Port\CustomerDirectory;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds lead responses, loading customer and assignee names for a whole page at once.
 */
final readonly class LeadPresenter
{
    public function __construct(private CustomerDirectory $customers, private UserDirectory $users)
    {
    }

    public function one(Lead $lead): LeadOutput
    {
        return $this->list([$lead])[0];
    }

    /**
     * @param list<Lead> $leads
     *
     * @return list<LeadOutput>
     */
    public function list(array $leads): array
    {
        $customers = $this->customers->names(array_map(static fn (Lead $l): string => $l->customerId(), $leads));
        $users = $this->users->names(array_map(static fn (Lead $l): ?string => $l->assignedUserId(), $leads));

        return array_map(static fn (Lead $l): LeadOutput => new LeadOutput(
            id: $l->id(),
            customer: new CustomerRefOutput($l->customerId(), $customers[$l->customerId()] ?? ''),
            source: $l->source(),
            status: $l->status(),
            receivedAt: $l->receivedAt(),
            createdAt: $l->createdAt(),
            channelNote: $l->channelNote(),
            assignee: null === $l->assignedUserId() ? null : new UserRefOutput($l->assignedUserId(), $users[$l->assignedUserId()] ?? ''),
            vehicleInterestText: $l->vehicleInterestText(),
            firstContactedAt: $l->firstContactedAt(),
            // The deal opened on LeadCaptured is the Deal context's; it is linked here once "deals" lands.
            dealId: null,
        ), $leads);
    }
}
