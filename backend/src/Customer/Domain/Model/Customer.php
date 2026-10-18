<?php

namespace App\Customer\Domain\Model;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A person the dealership sells to: the system of record (CRM-01).
 */
#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(name: 'idx_customers_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_customers_name', columns: ['dealership_id', 'last_name', 'first_name'])]
#[ORM\Index(name: 'idx_customers_sales1', columns: ['dealership_id', 'sales1_user_id'])]
#[ORM\HasLifecycleCallbacks]
class Customer
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 80, name: 'first_name')]
    private string $firstName;
    #[ORM\Column(type: 'string', length: 80, name: 'last_name')]
    private string $lastName;
    #[ORM\Column(type: 'string', length: 20, enumType: \App\Shared\Domain\Channel::class, name: 'preferred_channel', nullable: true)]
    private ?\App\Shared\Domain\Channel $preferredChannel;
    #[ORM\Column(type: 'guid', name: 'sales1_user_id', nullable: true)]
    private ?string $sales1UserId;
    #[ORM\Column(type: 'guid', name: 'sales2_user_id', nullable: true)]
    private ?string $sales2UserId;
    #[ORM\Column(type: 'guid', name: 'bdc_user_id', nullable: true)]
    private ?string $bdcUserId;
    #[ORM\Column(type: 'string', length: 40, name: 'source')]
    private string $source;
    #[ORM\Column(type: 'datetime_immutable', name: 'imported_at', nullable: true)]
    private ?\DateTimeImmutable $importedAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'archived_at', nullable: true)]
    private ?\DateTimeImmutable $archivedAt;
    /** @var Collection<int, CustomerContact> */
    #[ORM\OneToMany(targetEntity: CustomerContact::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $contacts;
    /** @var Collection<int, CustomerOwnedVehicle> */
    #[ORM\OneToMany(targetEntity: CustomerOwnedVehicle::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $ownedVehicles;

    public function __construct(
        string $dealershipId,
        string $firstName,
        string $lastName,
        ?\App\Shared\Domain\Channel $preferredChannel = null,
        ?string $sales1UserId = null,
        ?string $sales2UserId = null,
        ?string $bdcUserId = null,
        string $source = 'manual',
        ?\DateTimeImmutable $importedAt = null,
        ?\DateTimeImmutable $archivedAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->preferredChannel = $preferredChannel;
        $this->sales1UserId = $sales1UserId;
        $this->sales2UserId = $sales2UserId;
        $this->bdcUserId = $bdcUserId;
        $this->source = $source;
        $this->importedAt = $importedAt;
        $this->archivedAt = $archivedAt;
        $this->contacts = new ArrayCollection();
        $this->ownedVehicles = new ArrayCollection();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function preferredChannel(): ?\App\Shared\Domain\Channel
    {
        return $this->preferredChannel;
    }

    public function sales1UserId(): ?string
    {
        return $this->sales1UserId;
    }

    public function sales2UserId(): ?string
    {
        return $this->sales2UserId;
    }

    public function bdcUserId(): ?string
    {
        return $this->bdcUserId;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function importedAt(): ?\DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    /** @return Collection<int, CustomerContact> */
    public function contacts(): Collection
    {
        return $this->contacts;
    }

    /** @return Collection<int, CustomerOwnedVehicle> */
    public function ownedVehicles(): Collection
    {
        return $this->ownedVehicles;
    }

    /**
     * @throws InvalidValue invalid_name when either name is blank
     */
    public function rename(?string $firstName, ?string $lastName): void
    {
        $first = null === $firstName ? $this->firstName : trim($firstName);
        $last = null === $lastName ? $this->lastName : trim($lastName);
        if ('' === $first || '' === $last) {
            throw new InvalidValue('invalid_name', 'A customer needs a first and a last name.');
        }
        $this->firstName = $first;
        $this->lastName = $last;
    }

    public function prefer(?\App\Shared\Domain\Channel $channel): void
    {
        if (null !== $channel) {
            $this->preferredChannel = $channel;
        }
    }

    /**
     * @param string $value already normalised for its kind
     *
     * @throws Conflict duplicate_contact when this customer already has it
     */
    public function addContact(ContactKind $kind, string $value, ?string $label = null, bool $primary = false): CustomerContact
    {
        foreach ($this->contacts as $existing) {
            if ($existing->kind() === $kind && $existing->value() === $value) {
                throw new Conflict('duplicate_contact', 'This customer already has this contact.', ['customer_id' => $this->id, 'kind' => $kind->value]);
            }
        }
        $first = [] === $this->contactsOf($kind);
        $contact = new CustomerContact($this->dealershipId, $this, $kind, $value, self::blankToNull($label), false);
        $this->contacts->add($contact);
        if ($primary || $first) {
            $this->makePrimary($contact);
        }

        return $contact;
    }

    /**
     * @param string|null $value already normalised for the contact's kind, null to keep it
     *
     * @throws NotFound contact_not_found
     * @throws Conflict duplicate_contact when another contact of this customer already has the value
     */
    public function changeContact(string $contactId, ?string $value, ?string $label, ?bool $primary): CustomerContact
    {
        $contact = $this->contact($contactId);
        if (null !== $value && $value !== $contact->value()) {
            foreach ($this->contacts as $other) {
                if ($other !== $contact && $other->kind() === $contact->kind() && $other->value() === $value) {
                    throw new Conflict('duplicate_contact', 'This customer already has this contact.', ['customer_id' => $this->id, 'kind' => $contact->kind()->value]);
                }
            }
        }
        $contact->change($value ?? $contact->value(), null === $label ? $contact->label() : self::blankToNull($label));
        if (true === $primary) {
            $this->makePrimary($contact);
        }

        return $contact;
    }

    /**
     * Removes a contact; when it was the primary one the oldest remaining contact of its kind takes over.
     *
     * @throws NotFound contact_not_found
     */
    public function removeContact(string $contactId): void
    {
        $contact = $this->contact($contactId);
        $this->contacts->removeElement($contact);
        if ($contact->isPrimary()) {
            $next = $this->contactsOf($contact->kind())[0] ?? null;
            if (null !== $next) {
                $next->markPrimary(true);
            }
        }
    }

    /**
     * @throws NotFound contact_not_found
     */
    public function contact(string $contactId): CustomerContact
    {
        foreach ($this->contacts as $contact) {
            if ($contact->id() === $contactId) {
                return $contact;
            }
        }
        throw new NotFound('contact_not_found', 'The contact does not exist.', ['id' => $contactId]);
    }

    public function addOwnedVehicle(?int $year, ?string $make, ?string $model, ?string $trim, ?string $vin, ?\DateTimeImmutable $purchasedAt): CustomerOwnedVehicle
    {
        $vehicle = new CustomerOwnedVehicle(
            $this->dealershipId,
            $this,
            $year,
            self::blankToNull($make),
            self::blankToNull($model),
            self::blankToNull($trim),
            null === $vin ? null : (self::blankToNull(strtoupper($vin))),
            $purchasedAt,
        );
        if (null === $vehicle->year() && null === $vehicle->make() && null === $vehicle->model() && null === $vehicle->vin()) {
            throw new InvalidValue('vehicle_empty', 'Tell us the year, make, model or VIN of the vehicle.');
        }
        $this->ownedVehicles->add($vehicle);

        return $vehicle;
    }

    /**
     * @throws NotFound owned_vehicle_not_found
     */
    public function removeOwnedVehicle(string $vehicleId): void
    {
        foreach ($this->ownedVehicles as $vehicle) {
            if ($vehicle->id() === $vehicleId) {
                $this->ownedVehicles->removeElement($vehicle);

                return;
            }
        }
        throw new NotFound('owned_vehicle_not_found', 'The vehicle does not exist.', ['id' => $vehicleId]);
    }

    /**
     * Sets the three team slots together; a null empties its slot.
     */
    public function assign(?string $sales1UserId, ?string $sales2UserId, ?string $bdcUserId): void
    {
        $this->sales1UserId = $sales1UserId;
        $this->sales2UserId = $sales2UserId;
        $this->bdcUserId = $bdcUserId;
    }

    public function archive(\DateTimeImmutable $at): void
    {
        $this->archivedAt ??= $at;
    }

    /**
     * Marks the record as changed now, so lists ordered by recent activity move it up when only its parts changed.
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @return list<CustomerContact>
     */
    private function contactsOf(ContactKind $kind): array
    {
        return array_values(array_filter($this->contacts->toArray(), static fn (CustomerContact $c): bool => $c->kind() === $kind));
    }

    private function makePrimary(CustomerContact $contact): void
    {
        foreach ($this->contactsOf($contact->kind()) as $other) {
            $other->markPrimary($other === $contact);
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        $trimmed = null === $value ? '' : trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
