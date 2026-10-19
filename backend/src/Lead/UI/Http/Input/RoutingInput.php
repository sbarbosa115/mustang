<?php

namespace App\Lead\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RoutingInput
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        #[Assert\NotNull]
        #[Assert\All([new Assert\Uuid(strict: false)])]
        public ?array $userIds = null,
    ) {
    }
}
