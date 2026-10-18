<?php

namespace App\Customer\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class AssignmentsOutput
{
    public function __construct(
        public ?UserRefOutput $sales1 = null,
        public ?UserRefOutput $sales2 = null,
        public ?UserRefOutput $bdc = null,
    ) {
    }
}
