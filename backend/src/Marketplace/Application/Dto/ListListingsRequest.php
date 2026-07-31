<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Dto;

use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validatePriceRange')]
final readonly class ListListingsRequest
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
        #[Assert\PositiveOrZero]
        public ?int $minimumPrice = null,
        #[Assert\PositiveOrZero]
        public ?int $maximumPrice = null,
        #[Assert\Choice(callback: [ShippingMethodCode::class, 'values'])]
        public ?string $shippingMethod = null,
        public bool $pickupAllowed = false,
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 24,
    ) {
    }

    public function validatePriceRange(ExecutionContextInterface $context): void
    {
        if ($this->minimumPrice === null || $this->maximumPrice === null
            || $this->minimumPrice <= $this->maximumPrice) {
            return;
        }

        $context->buildViolation('De minimumprijs mag niet hoger zijn dan de maximumprijs.')
            ->atPath('minimumPrice')
            ->addViolation();
    }
}
