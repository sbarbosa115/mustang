<?php

namespace App\Compliance\Application\Command;

use App\Compliance\Application\Port\CustomerContacts;
use App\Compliance\Domain\Event\ConsentRecorded;
use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Model\ConsentState;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\PhoneNumber;

/**
 * Appends a granted record to the ledger (FR-11). A suppressed contact stays suppressed: only the customer's START
 * lifts a suppression.
 */
final readonly class RecordConsentHandler implements CommandHandler
{
    public function __construct(
        private ConsentRepository $consents,
        private CustomerContacts $customers,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RecordConsent $command): string
    {
        $contacts = $this->customers->contactsOf($command->dealershipId, $command->customerId)
            ?? throw new NotFound('customer_not_found', 'The customer does not exist.', ['id' => $command->customerId]);
        $value = self::normalise($command->channel, $command->contactValue);
        if (!\in_array($value, $contacts[Channel::Email === $command->channel ? 'email' : 'phone'], true)) {
            throw new InvalidValue('contact_not_on_customer', 'This contact is not one of the customer\'s.', ['contact_value' => $value]);
        }
        $now = $this->clock->now();
        $capturedAt = $this->capturedAt($command->capturedAt, $now);

        $consent = new Consent(
            $command->dealershipId,
            $command->customerId,
            $value,
            $command->channel,
            ConsentState::Granted,
            trim($command->source),
            $capturedAt,
            $command->capturedByUserId,
            self::text($command->capturedWhere),
            self::text($command->wording),
            self::text($command->campaign),
        );
        $this->consents->add($consent);
        $this->events->publish(new ConsentRecorded(
            $consent->id(),
            $command->dealershipId,
            $command->customerId,
            $value,
            $command->channel->value,
            $consent->source(),
            $command->capturedByUserId,
            $now,
        ));

        return $consent->id();
    }

    /**
     * Phones in E.164 for text and call, emails lowercase for email.
     */
    public static function normalise(Channel $channel, string $raw): string
    {
        return Channel::Email === $channel ? Email::fromString($raw)->value : PhoneNumber::fromString($raw)->e164;
    }

    private function capturedAt(?string $raw, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if (null === $raw || '' === trim($raw)) {
            return $now;
        }
        $at = \DateTimeImmutable::createFromFormat(\DATE_ATOM, trim($raw))
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vP', trim($raw))
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', trim($raw), new \DateTimeZone('UTC'))
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', trim($raw), new \DateTimeZone('UTC'));
        if (false === $at) {
            throw new InvalidValue('invalid_captured_at', 'When consent was captured must be a date and time (ISO 8601).', ['captured_at' => $raw]);
        }
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        if ($at > $now->modify('+5 minutes')) {
            throw new InvalidValue('captured_at_in_future', 'Consent cannot be captured in the future.', ['captured_at' => $raw]);
        }

        return $at;
    }

    private static function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
