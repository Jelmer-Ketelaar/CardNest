<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateWishlistRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $name,
    ) {
    }
}
