<?php

namespace App\DataImport\Domain\Model;

/**
 * What a spreadsheet import creates.
 */
enum ImportType: string
{
    case Customers = 'customers';
    case Leads = 'leads';
}
