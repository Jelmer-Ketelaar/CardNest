<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use App\Binder\Domain\Entity\BinderLayoutStrategy;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateRequest')]
final readonly class UpdateBinderRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 180)]
        public ?string $name = null,
        #[Assert\Choice(callback: [BinderLayoutStrategy::class, 'values'])]
        public ?string $layoutStrategy = null,
        #[Assert\Uuid]
        public ?string $parentLocationId = null,
        public bool $clearParentLocation = false,
    ) {
    }

    public function validateRequest(ExecutionContextInterface $context): void
    {
        if ($this->clearParentLocation && $this->parentLocationId !== null) {
            $context->buildViolation('De binderlocatie kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearParentLocation')
                ->addViolation();

            return;
        }

        if ($this->name !== null || $this->layoutStrategy !== null
            || $this->parentLocationId !== null || $this->clearParentLocation) {
            return;
        }

        $context->buildViolation('Geef minimaal één wijziging op.')->addViolation();
    }
}
