<?php

namespace App\Compliance\Application\Command;

use App\Compliance\Domain\Event\ConsentRevoked;
use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class RevokeConsentHandler implements CommandHandler
{
    public function __construct(
        private ConsentRepository $consents,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokeConsent $command): string
    {
        $consent = $this->consents->getIn($command->dealershipId, $command->consentId);
        $reason = null === $command->reason || '' === trim($command->reason) ? null : trim($command->reason);
        $now = $this->clock->now();

        $consent->revoke($now, $reason);
        $this->published($consent, $command->actorUserId, $now);
        foreach ($this->consents->grantedFor($command->dealershipId, $consent->contactValue(), $consent->channel()) as $other) {
            if ($other->id() !== $consent->id() && $other->isGranted()) {
                $other->revoke($now, $reason);
                $this->published($other, $command->actorUserId, $now);
            }
        }

        return $consent->id();
    }

    private function published(Consent $consent, ?string $actor, \DateTimeImmutable $now): void
    {
        $this->events->publish(new ConsentRevoked(
            $consent->id(),
            $consent->dealershipId(),
            $consent->customerId(),
            $consent->contactValue(),
            $consent->channel()->value,
            (string) $consent->revokedReason(),
            $actor,
            $now,
        ));
    }
}
