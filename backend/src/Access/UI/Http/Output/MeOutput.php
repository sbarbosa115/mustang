<?php

namespace App\Access\UI\Http\Output;

final readonly class MeOutput
{
    /**
     * @param list<string> $roles every role the user holds after the hierarchy, e.g. ["manager", "sales", "bdc"]
     */
    public function __construct(
        public UserOutput $user,
        public DealershipOutput $dealership,
        public array $roles,
    ) {
    }
}
