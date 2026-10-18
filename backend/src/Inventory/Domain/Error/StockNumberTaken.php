<?php

namespace App\Inventory\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class StockNumberTaken extends Conflict
{
    public function __construct(string $stockNumber)
    {
        parent::__construct('stock_number_taken', 'A vehicle with this stock number is already in the inventory.', ['stock_number' => $stockNumber]);
    }
}
