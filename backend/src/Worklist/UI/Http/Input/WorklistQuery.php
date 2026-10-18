<?php

namespace App\Worklist\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class WorklistQuery
{
    public function __construct(
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
    ) {
    }
}
