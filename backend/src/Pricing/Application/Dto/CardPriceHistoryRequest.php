<?php

declare(strict_types=1);

namespace App\Pricing\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CardPriceHistoryRequest
{
    public function __construct(
        #[Assert\Range(min: 2, max: 90)]
        public int $days = 30,
    ) {
    }
}
