<?php

namespace App\Messaging\Infrastructure\Consent;

use App\Messaging\Application\Port\ConsentCheck;
use App\Messaging\Application\Port\ConsentDecision;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Domain\Channel;

/**
 * The send check over the Compliance ledger's shared port: the latest row per customer and channel decides granted or
 * revoked, no row is missing, and a suppressed contact is opted out.
 */
final readonly class ConsentStatesConsentCheck implements ConsentCheck
{
    public function __construct(private ConsentStates $states, private Clock $clock)
    {
    }

    public function check(string $customerId, Channel $channel, string $contactValue): ConsentDecision
    {
        $state = $this->states->forCustomers([$customerId])[$customerId][$channel->value] ?? 'missing';

        return new ConsentDecision($state, $channel, $contactValue, $this->clock->now());
    }
}
