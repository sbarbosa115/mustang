<?php

namespace App\Customer\Application\Command;

use App\Customer\Application\TeamAssignmentPolicy;
use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class AssignTeamHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers, private TeamAssignmentPolicy $team)
    {
    }

    public function __invoke(AssignTeam $command): void
    {
        $customer = $this->customers->get($command->customerId);
        // Only a slot that changes is checked, so a customer keeps a rep who has since been deactivated.
        foreach ([
            ['sales1', $customer->sales1UserId(), $command->sales1UserId],
            ['sales2', $customer->sales2UserId(), $command->sales2UserId],
            ['bdc', $customer->bdcUserId(), $command->bdcUserId],
        ] as [$slot, $current, $wanted]) {
            if ($current !== $wanted) {
                $this->team->check($slot, $wanted);
            }
        }
        $customer->assign($command->sales1UserId, $command->sales2UserId, $command->bdcUserId);
        $customer->touch();
    }
}
