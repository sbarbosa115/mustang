<?php

namespace App\Inventory\Domain\Model;

/**
 * Whether a vehicle can be sold.
 */
enum VehicleStatus: string
{
    case Available = 'available';
    case Hold = 'hold';
    case Sold = 'sold';
    case InTransit = 'in_transit';
}
