<?php

namespace App\Customer\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A phone number (E.164) or email (lowercase) of a customer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_contacts')]
#[ORM\Index(name: 'idx_customer_contacts_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_contacts_lookup', columns: ['dealership_id', 'kind', 'value'])]
#[ORM\HasLifecycleCallbacks]
class CustomerContact
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;
    #[ORM\Column(type: 'string', length: 20, enumType: ContactKind::class, name: 'kind')]
    private ContactKind $kind;
    #[ORM\Column(type: 'string', length: 180, name: 'value')]
    private string $value;
    #[ORM\Column(type: 'string', length: 40, name: 'label', nullable: true)]
    private ?string $label;
    #[ORM\Column(type: 'boolean', name: 'is_primary')]
    private bool $isPrimary;

    public function __construct(
        string $dealershipId,
        Customer $customer,
        ContactKind $kind,
        string $value,
        ?string $label = null,
        bool $isPrimary = false,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customer = $customer;
        $this->kind = $kind;
        $this->value = $value;
        $this->label = $label;
        $this->isPrimary = $isPrimary;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function kind(): ContactKind
    {
        return $this->kind;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
