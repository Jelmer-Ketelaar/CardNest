<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Wishlist\Domain\Entity\WishlistPriority;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateMaxPrice')]
final readonly class AddWishlistItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $catalogCardId,
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public ?string $language = null,
        #[Assert\Uuid]
        public ?string $variantId = null,
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public ?string $minimumCondition = null,
        #[Assert\PositiveOrZero]
        public ?int $maxPrice = null,
        #[Assert\Choice(choices: ['EUR'])]
        public ?string $maxCurrency = null,
        #[Assert\Range(min: 1, max: 100)]
        public int $desiredQuantity = 1,
        #[Assert\Choice(callback: [WishlistPriority::class, 'values'])]
        public string $priority = 'normal',
        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
    ) {
    }

    public function validateMaxPrice(ExecutionContextInterface $context): void
    {
        if (($this->maxPrice === null) === ($this->maxCurrency === null)) {
            return;
        }

        $context->buildViolation('Maximumprijs en valuta moeten samen worden ingevuld.')
            ->atPath('maxPrice')
            ->addViolation();
    }
}
