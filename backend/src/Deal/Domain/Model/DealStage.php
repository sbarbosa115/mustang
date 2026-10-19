<?php

namespace App\Deal\Domain\Model;

/**
 * A deal stage, in sales order.
 */
enum DealStage: string
{
    case New = 'new';
    case Engaged = 'engaged';
    case Appointment = 'appointment';
    case Visit = 'visit';
    case Proposal = 'proposal';
    case Sold = 'sold';
    case Delivered = 'delivered';
    case Lost = 'lost';

    /**
     * Position in the sales order; lost sits outside it.
     */
    public function order(): int
    {
        return match ($this) {
            self::New => 0,
            self::Engaged => 1,
            self::Appointment => 2,
            self::Visit => 3,
            self::Proposal => 4,
            self::Sold => 5,
            self::Delivered => 6,
            self::Lost => -1,
        };
    }

    /**
     * Delivered and lost deals are closed; every other stage is an open deal.
     */
    public function isOpen(): bool
    {
        return self::Delivered !== $this && self::Lost !== $this;
    }
}
