<?php

declare(strict_types=1);

namespace App\Collection\Domain\Repository;

final readonly class CollectionItemSearchCriteria
{
    public function __construct(
        public ?string $cardId = null,
        public ?string $setId = null,
        public ?string $cardName = null,
        public ?string $cardNumber = null,
        public ?string $language = null,
        public ?string $variant = null,
        public ?string $condition = null,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
