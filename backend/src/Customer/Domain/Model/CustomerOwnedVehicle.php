<?php

namespace App\Customer\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A vehicle the customer owns today, a likely trade-in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_owned_vehicles')]
#[ORM\Index(name: 'idx_customer_owned_vehicles_dealership', columns: ['dealership_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerOwnedVehicle
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'ownedVehicles')]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;
    #[ORM\Column(type: 'integer', name: 'year', nullable: true)]
    private ?int $year;
    #[ORM\Column(type: 'string', length: 60, name: 'make', nullable: true)]
    private ?string $make;
    #[ORM\Column(type: 'string', length: 60, name: 'model', nullable: true)]
    private ?string $model;
    #[ORM\Column(type: 'string', length: 60, name: 'trim', nullable: true)]
    private ?string $trim;
    #[ORM\Column(type: 'string', length: 17, name: 'vin', nullable: true)]
    private ?string $vin;
    #[ORM\Column(type: 'datetime_immutable', name: 'purchased_at', nullable: true)]
    private ?\DateTimeImmutable $purchasedAt;

    public function __construct(
        string $dealershipId,
        Customer $customer,
        ?int $year = null,
        ?string $make = null,
        ?string $model = null,
        ?string $trim = null,
        ?string $vin = null,
        ?\DateTimeImmutable $purchasedAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customer = $customer;
        $this->year = $year;
        $this->make = $make;
        $this->model = $model;
        $this->trim = $trim;
        $this->vin = $vin;
        $this->purchasedAt = $purchasedAt;
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

    public function year(): ?int
    {
        return $this->year;
    }

    public function make(): ?string
    {
        return $this->make;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function trim(): ?string
    {
        return $this->trim;
    }

    public function vin(): ?string
    {
        return $this->vin;
    }

    public function purchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }
}
