<?php

namespace App\Customer\Domain\Model;

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
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $contacts;
    /** @var Collection<int, CustomerOwnedVehicle> */
    #[ORM\OneToMany(targetEntity: CustomerOwnedVehicle::class, mappedBy: 'customer', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
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
}
