<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardExternalReference;
use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\CatalogCardArtist;

final readonly class CatalogCardResponse
{
    public function __construct(private CatalogCard $card)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->card->getId()->toRfc4122(),
            'name' => $this->card->getName(),
            'number' => $this->card->getNumber(),
            'totalNumberInSet' => $this->card->getTotalNumberInSet(),
            'rarity' => $this->card->getRarity(),
            'cardType' => $this->card->getCardType(),
            'primaryCollectible' => $this->card->getPrimaryCollectible(),
            'illustrator' => $this->card->getIllustrator(),
            'artists' => array_map(
                static fn (CatalogCardArtist $artistLink): array => [
                    'id' => $artistLink->getArtist()->getId()->toRfc4122(),
                    'name' => $artistLink->getArtist()->getCanonicalName(),
                    'slug' => $artistLink->getArtist()->getSlug(),
                    'role' => $artistLink->getRole(),
                ],
                $this->card->getArtistLinks()->toArray(),
            ),
            'imageUrl' => $this->card->getImageUrl(),
            'thumbnailUrl' => $this->card->getThumbnailUrl(),
            'releaseDate' => $this->card->getReleaseDate()?->format('Y-m-d'),
            'metadata' => $this->card->getMetadata(),
            'set' => (new CardSetResponse($this->card->getSet()))->toArray(),
            'variants' => array_map(
                static fn (CardVariant $variant): array => (new CardVariantResponse($variant))->toArray(),
                $this->card->getVariants()->toArray(),
            ),
            'externalReferences' => array_map(
                static function (CardExternalReference $reference): array {
                    return (new CardExternalReferenceResponse($reference))->toArray();
                },
                $this->card->getExternalReferences()->toArray(),
            ),
            'createdAt' => $this->card->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->card->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
