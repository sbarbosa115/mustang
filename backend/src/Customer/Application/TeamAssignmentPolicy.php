<?php

namespace App\Customer\Application;

use App\Customer\Application\Port\TeamDirectory;
use App\Shared\Domain\Error\InvalidValue;

/**
 * Who may fill which team slot: Sales 1 and Sales 2 are salespeople (a manager may sell too), BDC is a BDC agent.
 */
final readonly class TeamAssignmentPolicy
{
    private const array ROLES = [
        'sales1' => ['sales', 'manager'],
        'sales2' => ['sales', 'manager'],
        'bdc' => ['bdc'],
    ];

    public function __construct(private TeamDirectory $team)
    {
    }

    /**
     * @throws InvalidValue assignee_not_found or assignee_wrong_role, with the slot in the detail
     */
    public function check(string $slot, ?string $userId): void
    {
        if (null === $userId) {
            return;
        }
        $role = $this->team->activeRoleOf($userId);
        if (null === $role) {
            throw new InvalidValue('assignee_not_found', 'This user is not an active member of the dealership.', ['slot' => $slot, 'user_id' => $userId]);
        }
        if (!\in_array($role, self::ROLES[$slot], true)) {
            throw new InvalidValue('assignee_wrong_role', \sprintf('A user with the role "%s" cannot fill this slot.', $role), ['slot' => $slot, 'user_id' => $userId, 'role' => $role]);
        }
    }
}
