<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignBinderSlotRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $collectionItemId,
    ) {
    }
}
