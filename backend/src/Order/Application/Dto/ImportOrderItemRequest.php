<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ImportOrderItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public string $language,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public string $condition,
        #[Assert\Uuid]
        public ?string $storageLocationId = null,
        #[Assert\Length(max: 5000)]
        public ?string $notes = null,
    ) {
    }
}
