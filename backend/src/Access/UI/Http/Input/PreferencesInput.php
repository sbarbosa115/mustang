<?php

namespace App\Access\UI\Http\Input;

use App\Access\Domain\Model\Theme;
use Symfony\Component\Validator\Constraints as Assert;

final class PreferencesInput
{
    public function __construct(
        #[Assert\NotNull]
        public ?Theme $theme = null,
    ) {
    }
}
