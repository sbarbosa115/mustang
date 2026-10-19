<?php

namespace App\Messaging\UI\Http\Input;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class ConversationListQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
        #[OA\Property(enum: ['all', 'unreplied', 'fumbled'])]
        #[Assert\Choice(choices: ['all', 'unreplied', 'fumbled'])]
        public ?string $folder = null,
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
    ) {
    }
}
