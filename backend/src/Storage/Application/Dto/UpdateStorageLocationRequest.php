<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use App\Storage\Domain\Entity\StorageLocationType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateRequest')]
final readonly class UpdateStorageLocationRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 180)]
        public ?string $name = null,
        #[Assert\Choice(callback: [StorageLocationType::class, 'values'])]
        public ?string $type = null,
        #[Assert\Uuid]
        public ?string $parentId = null,
        public bool $clearParent = false,
    ) {
    }

    public function validateRequest(ExecutionContextInterface $context): void
    {
        if ($this->clearParent && $this->parentId !== null) {
            $context->buildViolation('Een bovenliggende locatie kan niet tegelijk worden gewist en ingesteld.')
                ->atPath('clearParent')
                ->addViolation();

            return;
        }

        if ($this->name !== null || $this->type !== null || $this->parentId !== null || $this->clearParent) {
            return;
        }

        $context->buildViolation('Geef minimaal één wijziging op.')->addViolation();
    }
}
