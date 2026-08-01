<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\VariantCode;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchCatalogCardsRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $setId = null,
        #[Assert\Uuid]
        public ?string $seriesId = null,
        #[Assert\Length(max: 180)]
        public ?string $name = null,
        #[Assert\Length(max: 50)]
        public ?string $number = null,
        #[Assert\Length(max: 100)]
        public ?string $rarity = null,
        #[Assert\Choice(callback: [VariantCode::class, 'values'])]
        public ?string $variant = null,
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 20,
    ) {
    }
}
