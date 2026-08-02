<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Provider;

use App\Catalog\Domain\Entity\VariantCode;
use DateTimeImmutable;

/**
 * Provider-neutral representation of a catalog card. The complete source
 * payload is retained separately on CardExternalReference; metadata contains
 * the selected source fields useful to CardNest itself.
 *
 * @phpstan-type SourceMetadata array<string, mixed>
 */
final readonly class ImportedCatalogCard
{
    /**
     * @param list<VariantCode> $variants
     * @param SourceMetadata    $metadata
     * @param SourceMetadata    $rawData
     */
    public function __construct(
        public string $language,
        public string $externalId,
        public string $setExternalId,
        public string $name,
        public string $number,
        public ?string $totalNumberInSet,
        public string $rarity,
        public string $cardType,
        public ?string $primaryCollectible,
        public ?string $illustrator,
        public ?string $imageUrl,
        public ?string $thumbnailUrl,
        public ?DateTimeImmutable $releaseDate,
        public array $variants,
        public array $metadata,
        public array $rawData,
        public bool $isDetailed = false,
    ) {
    }
}
