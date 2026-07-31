<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCardSetsRequest
{
    public function __construct(
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 20,
    ) {
    }
}
