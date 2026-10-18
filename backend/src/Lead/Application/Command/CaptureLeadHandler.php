<?php

namespace App\Lead\Application\Command;

use App\Customer\Application\Command\CreateCustomer;
use App\Lead\Application\LeadAssigneePolicy;
use App\Lead\Application\Port\CustomerLookup;
use App\Lead\Application\Port\Router;
use App\Lead\Domain\Event\LeadCaptured;
use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Repository\LeadRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\PhoneNumber;

/**
 * Captures a lead (CRM-02): matches the customer by phone, then email, or creates one; routes it to a rep (the
 * explicit assignee, else round robin); raises LeadCaptured, on which the Deal context opens the deal.
 */
final readonly class CaptureLeadHandler implements CommandHandler
{
    public function __construct(
        private LeadRepository $leads,
        private CustomerLookup $customers,
        private Router $router,
        private LeadAssigneePolicy $assignees,
        private CommandBus $commands,
        private EventBus $events,
        private TenantContext $tenant,
        private Clock $clock,
    ) {
    }

    public function __invoke(CaptureLead $command): string
    {
        $now = $this->clock->now();
        $receivedAt = $command->receivedAt ?? $now;
        if ($receivedAt > $now->modify('+5 minutes')) {
            throw new InvalidValue('received_in_future', 'A lead cannot be received in the future.', ['field' => 'received_at']);
        }
        $phone = self::blank($command->phone) ? null : PhoneNumber::fromString((string) $command->phone)->e164;
        $email = self::blank($command->email) ? null : Email::fromString((string) $command->email)->value;

        if (null !== $command->assignedUserId) {
            $this->assignees->check($command->assignedUserId);
        }
        $assignee = $this->router->route($command->assignedUserId);

        $customerId = $this->customerFor($command, $phone, $email, $assignee);

        $lead = Lead::capture(
            $this->tenant->dealershipId(),
            $customerId,
            $command->source,
            $receivedAt,
            $assignee,
            $command->channelNote,
            $command->vehicleInterestText,
        );
        $this->leads->add($lead);
        $this->events->publish(new LeadCaptured(
            $lead->id(),
            $lead->dealershipId(),
            $customerId,
            $lead->source()->value,
            $assignee,
            $lead->vehicleInterestText(),
            $now,
        ));

        return $lead->id();
    }

    private function customerFor(CaptureLead $command, ?string $phone, ?string $email, ?string $assignee): string
    {
        if (null !== $command->customerId) {
            if (!$this->customers->exists($command->customerId)) {
                throw new InvalidValue('customer_not_found', 'This customer does not exist in your dealership.', ['field' => 'customer_id']);
            }

            return $command->customerId;
        }
        if (null === $phone && null === $email) {
            throw new InvalidValue('contact_required', 'Enter a phone number or an email.', ['field' => 'phone']);
        }
        $matched = $this->customers->findByContact($phone, $email);
        if (null !== $matched) {
            return $matched;
        }
        if (self::blank($command->firstName) || self::blank($command->lastName)) {
            throw new InvalidValue('customer_name_required', 'A new customer needs a first and a last name.', ['field' => 'first_name']);
        }

        return (string) $this->commands->dispatch(new CreateCustomer(
            (string) $command->firstName,
            (string) $command->lastName,
            $phone,
            $email,
            sales1UserId: null !== $assignee && $this->assignees->canSell($assignee) ? $assignee : null,
            source: $command->source->value,
        ));
    }

    private static function blank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
