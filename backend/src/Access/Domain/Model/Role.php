<?php

namespace App\Access\Domain\Model;

/**
 * What a user may do in their dealership.
 */
enum Role: string
{
    case Sales = 'sales';
    case Bdc = 'bdc';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * The Symfony role this maps to; role_hierarchy gives admin everything a manager has, and a manager what sales and BDC have.
     */
    public function securityRole(): string
    {
        return 'ROLE_'.strtoupper($this->value);
    }
}
