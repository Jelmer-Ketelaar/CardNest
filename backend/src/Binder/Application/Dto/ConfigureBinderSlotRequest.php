<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateRequest')]
final readonly class ConfigureBinderSlotRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $catalogCardId = null,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 180)]
        public ?string $label = null,
        public bool $clear = false,
    ) {
    }

    public function validateRequest(ExecutionContextInterface $context): void
    {
        $selected = ($this->catalogCardId !== null ? 1 : 0) + ($this->label !== null ? 1 : 0) + ($this->clear ? 1 : 0);
        if ($selected === 1) {
            return;
        }

        $context->buildViolation('Kies precies één cataloguskaart, label of wisactie.')->addViolation();
    }
}
