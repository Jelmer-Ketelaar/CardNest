<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validatePurchasePrice')]
#[Assert\Callback('validatePurchasedAt')]
final readonly class UpdateCollectionItemRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $variantId = null,
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public ?string $language = null,
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public ?string $condition = null,
        #[Assert\PositiveOrZero]
        public ?int $purchasePrice = null,
        #[Assert\Choice(choices: ['EUR'])]
        public ?string $purchaseCurrency = null,
        public bool $clearPurchasePrice = false,
        #[Assert\Date]
        public ?string $purchasedAt = null,
        public bool $clearPurchasedAt = false,
        #[Assert\Length(max: 180)]
        public ?string $sellerName = null,
        #[Assert\Length(max: 5000)]
        public ?string $notes = null,
    ) {
    }

    public function validatePurchasePrice(ExecutionContextInterface $context): void
    {
        if ($this->clearPurchasePrice && ($this->purchasePrice !== null || $this->purchaseCurrency !== null)) {
            $context->buildViolation('Een aankoopprijs kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearPurchasePrice')
                ->addViolation();

            return;
        }

        if (($this->purchasePrice === null) === ($this->purchaseCurrency === null)) {
            return;
        }

        $context->buildViolation('Aankoopprijs en valuta moeten samen worden ingevuld.')
            ->atPath('purchasePrice')
            ->addViolation();
    }

    public function validatePurchasedAt(ExecutionContextInterface $context): void
    {
        if (!$this->clearPurchasedAt || $this->purchasedAt === null) {
            return;
        }

        $context->buildViolation('Een aankoopdatum kan niet tegelijk worden gewist en ingesteld.')
            ->atPath('clearPurchasedAt')
            ->addViolation();
    }
}
