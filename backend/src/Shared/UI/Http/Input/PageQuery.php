<?php

namespace App\Shared\UI\Http\Input;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ?page=&per_page=&q= on every list. q is matched literally (see Like).
 */
final class PageQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        #[SerializedName('per_page')]
        public int $perPage = 25,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function term(): ?string
    {
        $term = null === $this->q ? '' : trim($this->q);

        return '' === $term ? null : $term;
    }
}
