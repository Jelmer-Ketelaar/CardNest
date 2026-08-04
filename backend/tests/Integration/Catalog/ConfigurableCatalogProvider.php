<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use RuntimeException;

final class ConfigurableCatalogProvider implements CardCatalogProvider
{
    public string $setName = 'Atomic set';

    public string $cardName = 'Mudkip';

    public string $setRawDataPadding = '';

    /** @var list<string> */
    public array $cardExternalIds = ['atomic-set-1'];

    public bool $failAfterFirstCard = false;

    public string $failureMessage = 'Provider failed after returning a partial set.';

    public function getProviderName(): string
    {
        return 'test-provider';
    }

    public function importSets(string $language, array $onlySetIds = []): iterable
    {
        if ($onlySetIds !== [] && !in_array('atomic-set', $onlySetIds, true)) {
            return;
        }

        yield new ImportedCardSet(
            language: $language,
            externalId: 'atomic-set',
            name: $this->setName,
            seriesExternalId: 'atomic-series',
            seriesName: 'Atomic series',
            printedTotal: count($this->cardExternalIds),
            total: count($this->cardExternalIds),
            releaseDate: null,
            logoUrl: null,
            symbolUrl: null,
            cardExternalIds: $this->cardExternalIds,
            rawData: ['name' => $this->setName, 'padding' => $this->setRawDataPadding],
        );
    }

    public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
    {
        if ($set->cardExternalIds !== []) {
            yield $this->card($set, $set->cardExternalIds[0]);
        }
        if ($this->failAfterFirstCard) {
            throw new RuntimeException($this->failureMessage);
        }

        foreach (array_slice($set->cardExternalIds, 1) as $externalId) {
            yield $this->card($set, $externalId);
        }
    }

    public function findCard(string $language, string $externalId): ?ImportedCatalogCard
    {
        return null;
    }

    private function card(ImportedCardSet $set, string $externalId): ImportedCatalogCard
    {
        return new ImportedCatalogCard(
            language: $set->language,
            externalId: $externalId,
            setExternalId: $set->externalId,
            name: $this->cardName,
            number: '1/1',
            totalNumberInSet: '1',
            rarity: 'Promo',
            cardType: 'Pokemon',
            primaryCollectible: 'Mudkip',
            illustrator: 'Yuka Morii',
            imageUrl: 'https://assets.example.test/atomic/1',
            thumbnailUrl: null,
            releaseDate: null,
            variants: [VariantCode::Promo],
            metadata: ['source' => 'test-provider'],
            rawData: ['name' => $this->cardName],
        );
    }
}
