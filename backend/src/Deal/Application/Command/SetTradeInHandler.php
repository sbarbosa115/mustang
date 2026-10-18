<?php

namespace App\Deal\Application\Command;

use App\Deal\Domain\Model\TradeIn;
use App\Deal\Domain\Repository\DealRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class SetTradeInHandler implements CommandHandler
{
    public function __construct(private DealRepository $deals)
    {
    }

    public function __invoke(SetTradeIn $command): void
    {
        $this->deals->get($command->dealId)->setTradeIn(new TradeIn($command->year, $command->make, $command->model, $command->mileage, $command->payoffCents));
    }
}
