<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardSeries;

final readonly class CardSeriesResponse
{
    public function __construct(private CardSeries $series)
    {
    }

    /** @return array{id: string, name: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->series->getId()->toRfc4122(),
            'name' => $this->series->getName(),
        ];
    }
}
