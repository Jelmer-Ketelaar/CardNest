<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Marketplace\Domain\Entity\ShippingMethodCode;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateShippingMethods')]
final readonly class CheckoutCartRequest
{
    /** @param array<string, string> $shippingMethods */
    public function __construct(
        #[Assert\Count(min: 1, max: 100)]
        public array $shippingMethods,
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $fullName,
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $addressLine1,
        #[Assert\Length(max: 180)]
        public ?string $addressLine2,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public string $postalCode,
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $city,
        #[Assert\Choice(choices: ['NL', 'BE'])]
        public string $countryCode,
    ) {
    }

    public function validateShippingMethods(ExecutionContextInterface $context): void
    {
        foreach ($this->shippingMethods as $sellerId => $shippingMethod) {
            if (!Uuid::isValid($sellerId)) {
                $context->buildViolation('Iedere verzendkeuze vereist een geldige verkoper-ID.')
                    ->atPath('shippingMethods')
                    ->addViolation();
            }
            if (!in_array($shippingMethod, ShippingMethodCode::values(), true)) {
                $context->buildViolation('Kies een ondersteunde verzendmethode.')
                    ->atPath('shippingMethods['.$sellerId.']')
                    ->addViolation();
            }
        }
    }
}
