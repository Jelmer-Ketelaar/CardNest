<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardSet;

final readonly class CardSetResponse
{
    public function __construct(private CardSet $set)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->set->getId()->toRfc4122(),
            'name' => $this->set->getName(),
            'code' => $this->set->getCode(),
            'series' => (new CardSeriesResponse($this->set->getSeries()))->toArray(),
            'language' => $this->set->getLanguage(),
            'releaseDate' => $this->set->getReleaseDate()?->format('Y-m-d'),
            'logoUrl' => $this->set->getLogoUrl(),
            'symbolUrl' => $this->set->getSymbolUrl(),
            'printedTotal' => $this->set->getPrintedTotal(),
            'total' => $this->set->getTotal(),
            'description' => $this->set->getDescription(),
            'status' => $this->set->getStatus()->value,
            'createdAt' => $this->set->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->set->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
