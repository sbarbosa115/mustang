<?php

namespace App\Lead\Infrastructure\Adapter;

use App\Customer\Application\Query\CustomerQueries;
use App\Lead\Application\Port\CustomerLookup;
use App\Shared\Domain\Error\NotFound;

/**
 * Matches through the Customer context's own queries: phone first, then email.
 */
final readonly class CustomerLookupAdapter implements CustomerLookup
{
    public function __construct(private CustomerQueries $customers)
    {
    }

    public function findByContact(?string $phone, ?string $email): ?string
    {
        foreach ([[$phone, null], [null, $email]] as [$byPhone, $byEmail]) {
            if (null === $byPhone && null === $byEmail) {
                continue;
            }
            $found = $this->customers->findByContact($byPhone, $byEmail);
            if ([] !== $found) {
                return $found[0]->id();
            }
        }

        return null;
    }

    public function exists(string $customerId): bool
    {
        try {
            $this->customers->byId($customerId);

            return true;
        } catch (NotFound) {
            return false;
        }
    }
}
