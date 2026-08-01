<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Wishlist\Domain\Entity\WishlistPriority;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateRequest')]
final readonly class UpdateWishlistItemRequest
{
    public function __construct(
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public ?string $language = null,
        public bool $clearLanguage = false,
        #[Assert\Uuid]
        public ?string $variantId = null,
        public bool $clearVariant = false,
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public ?string $minimumCondition = null,
        public bool $clearMinimumCondition = false,
        #[Assert\PositiveOrZero]
        public ?int $maxPrice = null,
        #[Assert\Choice(choices: ['EUR'])]
        public ?string $maxCurrency = null,
        public bool $clearMaxPrice = false,
        #[Assert\Range(min: 1, max: 100)]
        public ?int $desiredQuantity = null,
        #[Assert\Choice(callback: [WishlistPriority::class, 'values'])]
        public ?string $priority = null,
        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
        public bool $clearNotes = false,
    ) {
    }

    public function validateRequest(ExecutionContextInterface $context): void
    {
        $this->validateClearablePreference($context, 'clearLanguage', $this->clearLanguage, $this->language);
        $this->validateClearablePreference($context, 'clearVariant', $this->clearVariant, $this->variantId);
        $this->validateClearablePreference(
            $context,
            'clearMinimumCondition',
            $this->clearMinimumCondition,
            $this->minimumCondition,
        );
        $this->validateClearablePreference($context, 'clearNotes', $this->clearNotes, $this->notes);

        if ($this->clearMaxPrice && ($this->maxPrice !== null || $this->maxCurrency !== null)) {
            $context->buildViolation('Een maximumprijs kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearMaxPrice')
                ->addViolation();
        } elseif (($this->maxPrice === null) !== ($this->maxCurrency === null)) {
            $context->buildViolation('Maximumprijs en valuta moeten samen worden ingevuld.')
                ->atPath('maxPrice')
                ->addViolation();
        }

        if ($this->hasUpdate()) {
            return;
        }

        $context->buildViolation('Geef minimaal één wijziging op.')->addViolation();
    }

    private function validateClearablePreference(
        ExecutionContextInterface $context,
        string $property,
        bool $clear,
        ?string $value,
    ): void {
        if (!$clear || $value === null) {
            return;
        }

        $context->buildViolation('Deze voorkeur kan niet tegelijk worden gewist en ingesteld.')
            ->atPath($property)
            ->addViolation();
    }

    private function hasUpdate(): bool
    {
        return $this->language !== null || $this->clearLanguage
            || $this->variantId !== null || $this->clearVariant
            || $this->minimumCondition !== null || $this->clearMinimumCondition
            || $this->maxPrice !== null || $this->maxCurrency !== null || $this->clearMaxPrice
            || $this->desiredQuantity !== null || $this->priority !== null
            || $this->notes !== null || $this->clearNotes;
    }
}
