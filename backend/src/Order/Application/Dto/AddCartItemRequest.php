<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddCartItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $listingId,
    ) {
    }
}
