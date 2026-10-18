<?php

namespace App\Customer\UI\Http;

use App\Customer\Application\Query\CustomerQueries;
use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\CustomerContact;
use App\Customer\Domain\Model\CustomerOwnedVehicle;
use App\Customer\UI\Http\Output\AssignmentsOutput;
use App\Customer\UI\Http\Output\ContactOutput;
use App\Customer\UI\Http\Output\CustomerListItemOutput;
use App\Customer\UI\Http\Output\CustomerOutput;
use App\Customer\UI\Http\Output\OpenDealOutput;
use App\Customer\UI\Http\Output\OwnedVehicleOutput;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\ChannelConsentOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds customer responses, loading names, open deals and consent for a whole page at once.
 */
final readonly class CustomerPresenter
{
    public function __construct(
        private CustomerQueries $customers,
        private UserDirectory $users,
        private ConsentStates $consents,
        private Clock $clock,
    ) {
    }

    public function one(Customer $customer): CustomerOutput
    {
        $names = $this->users->names([$customer->sales1UserId(), $customer->sales2UserId(), $customer->bdcUserId()]);
        $deal = $this->customers->openDeals([$customer->id()])[$customer->id()] ?? null;
        $consent = $this->consents->forCustomers([$customer->id()])[$customer->id()];

        return new CustomerOutput(
            id: $customer->id(),
            firstName: $customer->firstName(),
            lastName: $customer->lastName(),
            name: self::name($customer),
            source: $customer->source(),
            contacts: array_values(array_map(
                static fn (CustomerContact $c): ContactOutput => new ContactOutput($c->id(), $c->kind(), $c->value(), $c->isPrimary(), $c->label()),
                $customer->contacts()->toArray(),
            )),
            ownedVehicles: array_values(array_map(
                static fn (CustomerOwnedVehicle $v): OwnedVehicleOutput => new OwnedVehicleOutput($v->id(), $v->year(), $v->make(), $v->model(), $v->trim(), $v->vin(), $v->purchasedAt()),
                $customer->ownedVehicles()->toArray(),
            )),
            assignments: new AssignmentsOutput(
                self::ref($customer->sales1UserId(), $names),
                self::ref($customer->sales2UserId(), $names),
                self::ref($customer->bdcUserId(), $names),
            ),
            consent: new ChannelConsentOutput($consent['text'], $consent['email'], $consent['call']),
            createdAt: $customer->createdAt(),
            updatedAt: $customer->updatedAt(),
            preferredChannel: $customer->preferredChannel(),
            openDeal: null === $deal ? null : new OpenDealOutput($deal['id'], $deal['stage'], $this->daysSince($deal['stage_entered_at']), $deal['vehicle_text']),
            archivedAt: $customer->archivedAt(),
        );
    }

    /**
     * @param list<Customer> $customers
     *
     * @return list<CustomerListItemOutput>
     */
    public function list(array $customers): array
    {
        $ids = array_map(static fn (Customer $c): string => $c->id(), $customers);
        $names = $this->users->names(array_map(static fn (Customer $c): ?string => $c->sales1UserId(), $customers));
        $deals = $this->customers->openDeals($ids);

        return array_map(static fn (Customer $c): CustomerListItemOutput => new CustomerListItemOutput(
            id: $c->id(),
            name: self::name($c),
            firstName: $c->firstName(),
            lastName: $c->lastName(),
            archived: null !== $c->archivedAt(),
            updatedAt: $c->updatedAt(),
            primaryPhone: self::primary($c, ContactKind::Phone),
            primaryEmail: self::primary($c, ContactKind::Email),
            sales1: self::ref($c->sales1UserId(), $names),
            openDealStage: $deals[$c->id()]['stage'] ?? null,
        ), $customers);
    }

    public static function name(Customer $customer): string
    {
        return trim($customer->firstName().' '.$customer->lastName());
    }

    private static function primary(Customer $customer, ContactKind $kind): ?string
    {
        $first = null;
        foreach ($customer->contacts() as $contact) {
            if ($contact->kind() !== $kind) {
                continue;
            }
            if ($contact->isPrimary()) {
                return $contact->value();
            }
            $first ??= $contact->value();
        }

        return $first;
    }

    /**
     * @param array<string, string> $names
     */
    private static function ref(?string $userId, array $names): ?UserRefOutput
    {
        return null === $userId ? null : new UserRefOutput($userId, $names[$userId] ?? '');
    }

    private function daysSince(\DateTimeImmutable $at): int
    {
        return max(0, (int) floor(($this->clock->now()->getTimestamp() - $at->getTimestamp()) / 86400));
    }
}
