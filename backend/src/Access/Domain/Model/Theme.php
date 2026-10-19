<?php

namespace App\Access\Domain\Model;

/**
 * The colour theme a user picked.
 */
enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
