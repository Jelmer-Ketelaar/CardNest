<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateRequest')]
final readonly class UpdateListingRequest
{
    public function __construct(
        #[Assert\Positive]
        public ?int $price = null,
        #[Assert\Choice(choices: ['EUR'])]
        public ?string $currency = null,
        #[Assert\Length(max: 5000)]
        public ?string $description = null,
        public bool $clearDescription = false,
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2048)]
        public ?string $frontImageUrl = null,
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2048)]
        public ?string $backImageUrl = null,
        #[Assert\DateTime(format: DATE_ATOM)]
        public ?string $expiresAt = null,
        public bool $clearExpiration = false,
    ) {
    }

    public function validateRequest(ExecutionContextInterface $context): void
    {
        if (($this->price === null) !== ($this->currency === null)) {
            $context->buildViolation('Prijs en valuta moeten samen worden ingevuld.')
                ->atPath('price')
                ->addViolation();
        }
        if (($this->frontImageUrl === null) !== ($this->backImageUrl === null)) {
            $context->buildViolation('Voor- en achterkantfoto moeten samen worden gewijzigd.')
                ->atPath('frontImageUrl')
                ->addViolation();
        }
        if ($this->clearDescription && $this->description !== null) {
            $context->buildViolation('De omschrijving kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearDescription')
                ->addViolation();
        }
        if ($this->clearExpiration && $this->expiresAt !== null) {
            $context->buildViolation('De einddatum kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearExpiration')
                ->addViolation();
        }
        if ($this->price !== null || $this->description !== null || $this->clearDescription
            || $this->frontImageUrl !== null || $this->expiresAt !== null || $this->clearExpiration) {
            return;
        }

        $context->buildViolation('Geef minimaal één wijziging op.')->addViolation();
    }
}
