<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlaceCollectionItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $locationId,
    ) {
    }
}
