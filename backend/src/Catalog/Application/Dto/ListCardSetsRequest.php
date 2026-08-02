<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogLanguage;
use App\Catalog\Domain\Repository\CardSetReleaseFilter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCardSetsRequest
{
    public function __construct(
        #[Assert\Length(max: 180)]
        public ?string $name = null,
        #[Assert\Choice(callback: [CatalogLanguage::class, 'values'])]
        public ?string $language = null,
        #[Assert\Choice(callback: [CardSetReleaseFilter::class, 'values'])]
        public string $release = 'all',
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 20,
    ) {
    }
}
