<?php

namespace App\Messaging\Infrastructure\Consent;

use App\Messaging\Application\Port\ConsentCheck;
use App\Messaging\Application\Port\ConsentDecision;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Domain\Channel;

/**
 * The send check over the Compliance ledger's shared port: the latest record of the contact on the channel decides
 * granted or revoked (and is named in the provenance), no record is missing, and a suppressed contact is opted out.
 */
final readonly class ConsentStatesConsentCheck implements ConsentCheck
{
    public function __construct(private ConsentStates $states, private Clock $clock)
    {
    }

    public function check(string $customerId, Channel $channel, string $contactValue): ConsentDecision
    {
        $decided = $this->states->forContact($customerId, $channel, $contactValue);

        return new ConsentDecision($decided['state'], $channel, $contactValue, $this->clock->now(), $decided['consent_id']);
    }
}
