<?php

declare(strict_types=1);

namespace App\Review\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListUserReviewsRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 20,
    ) {
    }
}
