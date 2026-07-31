<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardVariant;

final readonly class CardVariantResponse
{
    public function __construct(private CardVariant $variant)
    {
    }

    /** @return array{id: string, code: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->variant->getId()->toRfc4122(),
            'code' => $this->variant->getCode()->value,
        ];
    }
}
