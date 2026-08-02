<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Provider;

use DateTimeImmutable;

/**
 * Provider-neutral representation of one localized official card set.
 *
 * @phpstan-type RawData array<string, mixed>
 */
final readonly class ImportedCardSet
{
    /**
     * @param list<string> $cardExternalIds
     * @param RawData      $rawData
     */
    public function __construct(
        public string $language,
        public string $externalId,
        public string $name,
        public string $seriesExternalId,
        public string $seriesName,
        public int $printedTotal,
        public int $total,
        public ?DateTimeImmutable $releaseDate,
        public ?string $logoUrl,
        public ?string $symbolUrl,
        public array $cardExternalIds,
        public array $rawData,
    ) {
    }
}
