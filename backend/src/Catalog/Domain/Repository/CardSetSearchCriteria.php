<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

final readonly class CardSetSearchCriteria
{
    public function __construct(
        public ?string $name = null,
        public ?string $language = null,
        public CardSetReleaseFilter $release = CardSetReleaseFilter::All,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
