<?php

namespace App\Worklist\Domain\Model;

use App\Shared\Domain\Channel;

/**
 * One row of the contact-now list: the customer, the reason that won and the channel to reach them on.
 */
final readonly class Priority
{
    public function __construct(
        public CustomerFacts $customer,
        public Reason $reason,
        public Channel $suggestedChannel,
    ) {
    }
}
