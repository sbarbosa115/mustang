<?php

namespace App\DataImport\Application\Import;

/**
 * What one imported row did.
 */
enum RowOutcome
{
    /** A new customer (customers file) or a new lead (leads file). */
    case Created;
    /** A duplicate of an existing customer that brought something new. */
    case Updated;
    /** A duplicate with nothing new. */
    case Skipped;
}
