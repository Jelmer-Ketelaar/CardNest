<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Repository;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Marketplace\Domain\Entity\ShippingMethodCode;

final readonly class ListingSearchCriteria
{
    public function __construct(
        public ?string $cardId = null,
        public ?string $setId = null,
        public ?string $cardName = null,
        public ?string $cardNumber = null,
        public ?CardLanguage $language = null,
        public ?string $variant = null,
        public ?CardCondition $condition = null,
        public ?int $minimumPrice = null,
        public ?int $maximumPrice = null,
        public ?ShippingMethodCode $shippingMethod = null,
        public bool $pickupAllowed = false,
        public int $page = 1,
        public int $perPage = 24,
    ) {
    }
}
