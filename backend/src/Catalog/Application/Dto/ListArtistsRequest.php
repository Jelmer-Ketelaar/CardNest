<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListArtistsRequest
{
    public function __construct(
        #[Assert\Length(max: 180)]
        public string $q = '',
        #[Assert\Choice(choices: ['name', 'card_count'])]
        public string $sort = 'name',
        public bool $withCardsOnly = true,
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 24,
    ) {
    }
}
