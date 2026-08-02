<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogImportResult;
use App\Catalog\Application\Dto\ImportCatalogRequest;
use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use InvalidArgumentException;
use RuntimeException;

/**
 * Imports catalog source data in bounded Doctrine batches. Existing catalog
 * cards are identified by their provider reference, so changes in a source's
 * display number cannot create duplicate CardNest records.
 */
final readonly class ImportCatalog
{
    private const string SOURCE_NAME = 'tcgdex';

    public function __construct(
        private CardCatalogProvider $provider,
        private CatalogImportStore $store,
    ) {
    }

    /**
     * @param callable(string, string, int): void|null $reportSet
     */
    public function execute(ImportCatalogRequest $request, ?callable $reportSet = null): CatalogImportResult
    {
        $result = new CatalogImportResult();

        foreach ($request->languages as $language) {
            foreach ($this->provider->importSets($language, $request->onlySetIds) as $importedSet) {
                $set = $this->importSet($importedSet, $result);
                $this->store->flush();
                $pendingCards = 0;

                foreach ($this->provider->importCards($importedSet, $request->includeCardDetails) as $importedCard) {
                    if ($importedCard->setExternalId !== $importedSet->externalId) {
                        throw new RuntimeException('The catalog provider returned a card for a different set.');
                    }

                    $this->importCard($set, $importedCard, $result);
                    ++$pendingCards;

                    if ($pendingCards < $request->batchSize) {
                        continue;
                    }

                    $this->store->flush();
                    $this->store->clear();
                    $set = $this->store->findSetByCode($this->setCode($importedSet))
                        ?? throw new RuntimeException('The imported card set could not be reloaded.');
                    $pendingCards = 0;
                }

                $this->store->flush();
                $this->store->clear();
                if ($reportSet !== null) {
                    $reportSet($language, $importedSet->externalId, count($importedSet->cardExternalIds));
                }
            }
        }

        return $result;
    }

    private function importSet(ImportedCardSet $importedSet, CatalogImportResult $result): CardSet
    {
        $setCode = $this->setCode($importedSet);
        $existingSet = $this->store->findSetByCode($setCode);
        if ($existingSet instanceof CardSet) {
            $result->recordExistingSet();

            return $existingSet;
        }

        $seriesSourceKey = sprintf('%s:series:%s', self::SOURCE_NAME, mb_strtolower($importedSet->seriesExternalId));
        $series = $this->store->findSeriesBySourceKey($seriesSourceKey);
        if (!$series instanceof CardSeries) {
            $existingSeriesWithName = $this->store->findSeriesByName($importedSet->seriesName);
            if ($existingSeriesWithName instanceof CardSeries && $existingSeriesWithName->getSourceKey() === null) {
                $series = $existingSeriesWithName;
            } else {
                $seriesName = $existingSeriesWithName instanceof CardSeries
                    ? sprintf('%s [%s]', $importedSet->seriesName, $importedSet->seriesExternalId)
                    : $importedSet->seriesName;
                $series = new CardSeries($seriesName, $seriesSourceKey);
                $this->store->persist($series);
            }
        }

        $set = new CardSet(
            series: $series,
            name: $importedSet->name,
            code: $setCode,
            language: $importedSet->language,
            printedTotal: $importedSet->printedTotal,
            total: $importedSet->total,
            status: CardSetStatus::Active,
            releaseDate: $importedSet->releaseDate,
            logoUrl: $importedSet->logoUrl,
            symbolUrl: $importedSet->symbolUrl,
        );
        $this->store->persist($set);
        $result->recordCreatedSet();

        return $set;
    }

    private function importCard(
        CardSet $set,
        ImportedCatalogCard $importedCard,
        CatalogImportResult $result,
    ): void {
        $externalId = $this->cardExternalId($importedCard);
        $card = $this->store->findCardByExternalReference(self::SOURCE_NAME, $externalId);
        if (!$card instanceof CatalogCard) {
            $card = new CatalogCard(
                set: $set,
                name: $importedCard->name,
                number: $importedCard->number,
                rarity: $importedCard->rarity,
                cardType: $importedCard->cardType,
                totalNumberInSet: $importedCard->totalNumberInSet,
                primaryCollectible: $importedCard->primaryCollectible,
                illustrator: $importedCard->illustrator,
                imageUrl: $importedCard->imageUrl,
                thumbnailUrl: $importedCard->thumbnailUrl,
                releaseDate: $importedCard->releaseDate ?? $set->getReleaseDate(),
                metadata: $importedCard->metadata,
            );

            foreach ($importedCard->variants as $variant) {
                $card->addVariant($variant);
            }

            $this->attachImportedArtist($card, $importedCard->illustrator);

            $card->addExternalReference(self::SOURCE_NAME, $externalId, $importedCard->rawData);
            $this->store->persist($card);
            $result->recordCreatedCard();

            return;
        }

        foreach ($card->getExternalReferences() as $reference) {
            if ($reference->getSourceName() === self::SOURCE_NAME && $reference->getExternalId() === $externalId) {
                if ($importedCard->isDetailed) {
                    if ($reference->getCorrectedData() === null) {
                        $card->synchronizeFromProvider(
                            name: $importedCard->name,
                            rarity: $importedCard->rarity,
                            cardType: $importedCard->cardType,
                            primaryCollectible: $importedCard->primaryCollectible,
                            illustrator: $importedCard->illustrator,
                            imageUrl: $importedCard->imageUrl,
                            thumbnailUrl: $importedCard->thumbnailUrl,
                            variants: $importedCard->variants,
                            metadata: $importedCard->metadata,
                        );
                        $this->attachImportedArtist($card, $importedCard->illustrator);
                    }
                    $reference->markSynced($importedCard->rawData);
                } elseif (!$this->containsDetailedSourceData($reference->getRawData())) {
                    $reference->markSynced($importedCard->rawData);
                }
                $this->store->persist($card);
                $result->recordSyncedCard();

                return;
            }
        }

        $card->addExternalReference(self::SOURCE_NAME, $externalId, $importedCard->rawData);
        $this->store->persist($card);
        $result->recordSyncedCard();
    }

    private function setCode(ImportedCardSet $set): string
    {
        $code = sprintf('%s:%s:%s', self::SOURCE_NAME, $set->language, $set->externalId);
        if (mb_strlen($code) > 50) {
            throw new InvalidArgumentException('The external set identifier exceeds the CardSet code limit.');
        }

        return $code;
    }

    private function cardExternalId(ImportedCatalogCard $card): string
    {
        $externalId = sprintf('%s:%s', $card->language, $card->externalId);
        if (mb_strlen($externalId) > 180) {
            throw new InvalidArgumentException('The external card identifier exceeds the catalog reference limit.');
        }

        return $externalId;
    }

    /** @param array<string, mixed> $rawData */
    private function containsDetailedSourceData(array $rawData): bool
    {
        return array_key_exists('category', $rawData) || array_key_exists('variants', $rawData);
    }

    private function attachImportedArtist(CatalogCard $card, ?string $illustrator): void
    {
        if ($illustrator === null || Artist::canonicalizeName($illustrator) === '') {
            return;
        }

        $normalizedName = Artist::normalizeName($illustrator);
        $artist = $this->store->findArtistByNormalizedName($normalizedName);
        if (!$artist instanceof Artist) {
            $artist = Artist::fromCanonicalName($illustrator);
            $this->store->persist($artist);
        }

        $card->addArtist($artist);
    }
}
