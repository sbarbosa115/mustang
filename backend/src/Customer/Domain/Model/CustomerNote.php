<?php

namespace App\Customer\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A note a user wrote on a customer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_notes')]
#[ORM\Index(name: 'idx_customer_notes_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_notes_customer', columns: ['dealership_id', 'customer_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerNote
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'guid', name: 'author_user_id')]
    private string $authorUserId;
    #[ORM\Column(type: 'text', name: 'body')]
    private string $body;

    public function __construct(
        string $dealershipId,
        string $customerId,
        string $authorUserId,
        string $body,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->authorUserId = $authorUserId;
        $this->body = $body;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function authorUserId(): string
    {
        return $this->authorUserId;
    }

    public function body(): string
    {
        return $this->body;
    }
}
