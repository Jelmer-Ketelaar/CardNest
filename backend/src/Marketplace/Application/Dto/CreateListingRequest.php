<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateShipping')]
final readonly class CreateListingRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $collectionItemId,
        #[Assert\Positive]
        public int $price,
        #[Assert\Choice(choices: ['EUR'])]
        public string $currency,
        #[Assert\Length(max: 5000)]
        public ?string $description = null,
        #[Assert\PositiveOrZero]
        public ?int $letterMailPrice = null,
        #[Assert\PositiveOrZero]
        public ?int $parcelPrice = null,
        public bool $pickupAllowed = false,
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2048)]
        public string $frontImageUrl = '',
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2048)]
        public string $backImageUrl = '',
        #[Assert\DateTime(format: DATE_ATOM)]
        public ?string $expiresAt = null,
    ) {
    }

    public function validateShipping(ExecutionContextInterface $context): void
    {
        if ($this->letterMailPrice !== null || $this->parcelPrice !== null || $this->pickupAllowed) {
            return;
        }

        $context->buildViolation('Kies minimaal één verzend- of afhaaloptie.')
            ->atPath('letterMailPrice')
            ->addViolation();
    }
}
