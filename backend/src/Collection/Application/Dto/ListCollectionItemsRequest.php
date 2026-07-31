<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItemStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCollectionItemsRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $cardId = null,
        #[Assert\Uuid]
        public ?string $setId = null,
        #[Assert\Length(max: 180)]
        public ?string $cardName = null,
        #[Assert\Length(max: 50)]
        public ?string $cardNumber = null,
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public ?string $language = null,
        #[Assert\Choice(callback: [VariantCode::class, 'values'])]
        public ?string $variant = null,
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public ?string $condition = null,
        #[Assert\Choice(callback: [CollectionItemStatus::class, 'values'])]
        public ?string $status = 'in_collection',
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 20,
    ) {
    }
}
