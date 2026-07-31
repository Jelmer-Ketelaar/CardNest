<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use App\Storage\Domain\Entity\StorageLocationType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateStorageLocationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [StorageLocationType::class, 'values'])]
        public string $type,
        #[Assert\Uuid]
        public ?string $parentId = null,
    ) {
    }
}
