<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use App\Binder\Domain\Entity\BinderLayoutStrategy;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateSlotCount')]
final readonly class CreateBinderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $name,
        #[Assert\Range(min: 1, max: 6)]
        public int $rows,
        #[Assert\Range(min: 1, max: 6)]
        public int $columns,
        #[Assert\Range(min: 1, max: 100)]
        public int $pageCount,
        public bool $doubleSided = false,
        #[Assert\Choice(callback: [BinderLayoutStrategy::class, 'values'])]
        public string $layoutStrategy = 'custom',
        #[Assert\Uuid]
        public ?string $parentLocationId = null,
    ) {
    }

    public function validateSlotCount(ExecutionContextInterface $context): void
    {
        $slotCount = $this->rows * $this->columns * $this->pageCount * ($this->doubleSided ? 2 : 1);
        if ($slotCount <= 2000) {
            return;
        }

        $context->buildViolation('Een binder kan maximaal 2000 vakken bevatten.')
            ->atPath('pageCount')
            ->addViolation();
    }
}
