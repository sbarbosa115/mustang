<?php

namespace App\DataImport\Infrastructure\Adapter;

use App\Compliance\Application\Command\RecordConsent;
use App\DataImport\Application\Port\ConsentLedger;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Domain\Channel;

/**
 * Through the Compliance context's RecordConsent (which takes the dealership explicitly) and the shared consent states.
 */
final readonly class ConsentLedgerAdapter implements ConsentLedger
{
    public function __construct(private CommandBus $commands, private ConsentStates $states)
    {
    }

    public function isMissing(string $customerId, Channel $channel, string $contactValue): bool
    {
        return 'missing' === $this->states->forContact($customerId, $channel, $contactValue)['state'];
    }

    public function record(
        string $dealershipId,
        string $customerId,
        Channel $channel,
        string $contactValue,
        string $source,
        string $wording,
        ?string $capturedWhere,
        ?string $capturedByUserId,
    ): void {
        $this->commands->dispatch(new RecordConsent(
            $dealershipId,
            $customerId,
            $contactValue,
            $channel,
            $source,
            $capturedByUserId,
            $capturedWhere,
            $wording,
        ));
    }
}
