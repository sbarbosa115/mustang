<?php

namespace App\Inventory\Domain\Model;

/**
 * New, used or certified pre-owned.
 */
enum VehicleCondition: string
{
    case New = 'new';
    case Used = 'used';
    case Cpo = 'cpo';
}
