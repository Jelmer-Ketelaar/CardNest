<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;

final class TwoSetCatalogProvider implements CardCatalogProvider
{
    public function getProviderName(): string
    {
        return 'two-set-provider';
    }

    public function importSets(string $language, array $onlySetIds = []): iterable
    {
        yield $this->set($language, 'first-set', 'First set');
        yield $this->set($language, 'second-set', 'Second set');
    }

    public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
    {
        yield new ImportedCatalogCard(
            language: $set->language,
            externalId: $set->cardExternalIds[0],
            setExternalId: $set->externalId,
            name: $set->externalId === 'first-set' ? 'First card' : 'Second card',
            number: '1/1',
            totalNumberInSet: '1',
            rarity: 'Promo',
            cardType: 'Pokemon',
            primaryCollectible: 'Mudkip',
            illustrator: 'Yuka Morii',
            imageUrl: null,
            thumbnailUrl: null,
            releaseDate: null,
            variants: [VariantCode::Promo],
            metadata: ['source' => 'two-set-provider'],
            rawData: ['id' => $set->cardExternalIds[0]],
        );
    }

    public function findCard(string $language, string $externalId): ?ImportedCatalogCard
    {
        return null;
    }

    private function set(string $language, string $externalId, string $name): ImportedCardSet
    {
        return new ImportedCardSet(
            language: $language,
            externalId: $externalId,
            name: $name,
            seriesExternalId: 'atomic-series',
            seriesName: 'Atomic series',
            printedTotal: 1,
            total: 1,
            releaseDate: null,
            logoUrl: null,
            symbolUrl: null,
            cardExternalIds: [$externalId.'-1'],
            rawData: ['id' => $externalId],
        );
    }
}
