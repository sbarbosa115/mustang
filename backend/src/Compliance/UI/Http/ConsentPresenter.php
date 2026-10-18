<?php

namespace App\Compliance\UI\Http;

use App\Compliance\Domain\Model\Consent;
use App\Compliance\UI\Http\Output\ConsentOutput;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds consent responses, loading the names of whoever captured them in one call.
 */
final readonly class ConsentPresenter
{
    public function __construct(private UserDirectory $users)
    {
    }

    /**
     * @param list<Consent> $consents
     *
     * @return list<ConsentOutput>
     */
    public function many(array $consents): array
    {
        $names = $this->users->names(array_map(static fn (Consent $c): ?string => $c->capturedByUserId(), $consents));

        return array_map(static fn (Consent $c): ConsentOutput => new ConsentOutput(
            $c->id(),
            $c->customerId(),
            $c->contactValue(),
            $c->channel(),
            $c->state(),
            $c->source(),
            $c->capturedAt(),
            null === $c->capturedByUserId() ? null : new UserRefOutput($c->capturedByUserId(), $names[$c->capturedByUserId()] ?? ''),
            $c->capturedWhere(),
            $c->wording(),
            $c->campaign(),
            $c->revokedAt(),
            $c->revokedReason(),
        ), $consents);
    }

    public function one(Consent $consent): ConsentOutput
    {
        return $this->many([$consent])[0];
    }
}
