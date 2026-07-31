<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validatePurchasePrice')]
final readonly class CreateCollectionItemRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $catalogCardId,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $variantId,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [CardLanguage::class, 'values'])]
        public string $language,
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [CardCondition::class, 'values'])]
        public string $condition,
        #[Assert\Range(min: 1, max: 100)]
        public int $quantity = 1,
        #[Assert\PositiveOrZero]
        public ?int $purchasePrice = null,
        #[Assert\Choice(choices: ['EUR'])]
        public ?string $purchaseCurrency = null,
        #[Assert\Date]
        public ?string $purchasedAt = null,
        #[Assert\Length(max: 180)]
        public ?string $sellerName = null,
        #[Assert\Length(max: 5000)]
        public ?string $notes = null,
    ) {
    }

    public function validatePurchasePrice(ExecutionContextInterface $context): void
    {
        if (($this->purchasePrice === null) === ($this->purchaseCurrency === null)) {
            return;
        }

        $context->buildViolation('Aankoopprijs en valuta moeten samen worden ingevuld.')
            ->atPath('purchasePrice')
            ->addViolation();
    }
}
