<?php

namespace App\Compliance\Application\Command;

use App\Compliance\Domain\Event\ConsentRecorded;
use App\Compliance\Domain\Event\ConsentRevoked;
use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Model\ConsentState;
use App\Compliance\Domain\Model\SmsKeyword;
use App\Compliance\Domain\Model\Suppression;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Compliance\Domain\Repository\SuppressionList;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Channel;

/**
 * STOP (STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT) revokes text and call for that phone in the dealership and puts the
 * phone on the global suppression list, so no dealership can text it (FR-12). START (UNSTOP) lifts the suppression
 * and records text consent with source sms_keyword.
 */
final readonly class ApplyKeywordHandler implements CommandHandler
{
    public const string SOURCE = 'sms_keyword';
    public const string STOP_REASON = 'stop_keyword';
    private const string WHERE = 'Text message reply';

    public function __construct(
        private ConsentRepository $consents,
        private SuppressionList $suppressions,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ApplyKeyword $command): ?string
    {
        $keyword = SmsKeyword::fromMessage($command->body);
        match ($keyword) {
            SmsKeyword::Stop => $this->stop($command),
            SmsKeyword::Start => $this->start($command),
            null => null,
        };

        return $keyword?->value;
    }

    private function stop(ApplyKeyword $command): void
    {
        $now = $this->clock->now();
        foreach ([Channel::Text, Channel::Call] as $channel) {
            $revoked = 0;
            foreach ($this->consents->grantedFor($command->dealershipId, $command->phone, $channel) as $consent) {
                $consent->revoke($now, self::STOP_REASON);
                $this->revoked($consent, $now);
                ++$revoked;
            }
            if (0 === $revoked && Channel::Text === $channel) {
                // Nothing to revoke: the opt-out is still written down, with the customer's own words.
                $consent = new Consent(
                    $command->dealershipId,
                    $command->customerId,
                    $command->phone,
                    Channel::Text,
                    ConsentState::Revoked,
                    self::SOURCE,
                    $now,
                    capturedWhere: self::WHERE,
                    wording: trim($command->body),
                    revokedAt: $now,
                    revokedReason: self::STOP_REASON,
                );
                $this->consents->add($consent);
                $this->revoked($consent, $now);
            }
        }

        if (null === $this->suppressions->find($command->phone, Channel::Text)) {
            $this->suppressions->add(new Suppression($command->phone, Channel::Text, self::STOP_REASON));
        }
    }

    private function start(ApplyKeyword $command): void
    {
        $suppression = $this->suppressions->find($command->phone, Channel::Text);
        if (null !== $suppression) {
            $this->suppressions->remove($suppression);
        }
        $now = $this->clock->now();
        $consent = new Consent(
            $command->dealershipId,
            $command->customerId,
            $command->phone,
            Channel::Text,
            ConsentState::Granted,
            self::SOURCE,
            $now,
            capturedWhere: self::WHERE,
            wording: trim($command->body),
        );
        $this->consents->add($consent);
        $this->events->publish(new ConsentRecorded($consent->id(), $command->dealershipId, $command->customerId, $command->phone, Channel::Text->value, self::SOURCE, null, $now));
    }

    private function revoked(Consent $consent, \DateTimeImmutable $now): void
    {
        $this->events->publish(new ConsentRevoked(
            $consent->id(),
            $consent->dealershipId(),
            $consent->customerId(),
            $consent->contactValue(),
            $consent->channel()->value,
            self::STOP_REASON,
            null,
            $now,
        ));
    }
}
