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
}
