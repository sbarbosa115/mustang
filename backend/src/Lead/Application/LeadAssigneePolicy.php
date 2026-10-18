<?php

namespace App\Lead\Application;

use App\Lead\Application\Port\SalesTeam;
use App\Shared\Domain\Error\InvalidValue;

/**
 * Who a lead can be handed to: an active salesperson, BDC agent or manager of the dealership.
 */
final readonly class LeadAssigneePolicy
{
    private const array ROLES = ['sales', 'bdc', 'manager'];

    public function __construct(private SalesTeam $team)
    {
    }

    /**
     * @throws InvalidValue assignee_not_found or assignee_wrong_role
     */
    public function check(string $userId): void
    {
        $role = $this->team->activeRoleOf($userId);
        if (null === $role) {
            throw new InvalidValue('assignee_not_found', 'This user is not an active member of the dealership.', ['field' => 'user_id', 'user_id' => $userId]);
        }
        if (!\in_array($role, self::ROLES, true)) {
            throw new InvalidValue('assignee_wrong_role', \sprintf('A user with the role "%s" cannot take leads.', $role), ['field' => 'user_id', 'user_id' => $userId, 'role' => $role]);
        }
    }

    /**
     * Whether the user can be a customer's Sales 1 (the Customer context's rule: sales or manager).
     */
    public function canSell(string $userId): bool
    {
        return \in_array($this->team->activeRoleOf($userId), ['sales', 'manager'], true);
    }
}
