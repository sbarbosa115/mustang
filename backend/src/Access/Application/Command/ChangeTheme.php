<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Model\Theme;

final readonly class ChangeTheme
{
    public function __construct(public string $userId, public Theme $theme)
    {
    }
}
