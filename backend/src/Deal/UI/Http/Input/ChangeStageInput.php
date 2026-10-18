<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangeStageInput
{
    public function __construct(
        #[Assert\NotNull]
        public ?\App\Deal\Domain\Model\DealStage $stage = null,
        #[Assert\Length(max: 255)]
        public ?string $lostReason = null,
    ) {
    }
}
