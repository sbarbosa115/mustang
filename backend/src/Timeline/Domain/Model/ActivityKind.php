<?php

namespace App\Timeline\Domain\Model;

/**
 * What a timeline entry records.
 */
enum ActivityKind: string
{
    case Lead = 'lead';
    case MessageIn = 'message_in';
    case MessageOut = 'message_out';
    case Stage = 'stage';
    case Appointment = 'appointment';
    case Note = 'note';
    case Consent = 'consent';
    case Import = 'import';
}
