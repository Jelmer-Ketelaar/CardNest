<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Dto\ImportCatalogRequest;
use App\Catalog\Application\ImportCatalog;
use App\Catalog\Domain\Entity\CardExternalReference;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Catalog\Infrastructure\Import\DoctrineCatalogImportStore;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportCatalogTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testItCreatesCatalogRecordsOnceAndRefreshesRawSourceDataOnLaterImports(): void
    {
        // Arrange
        $provider = new class implements CardCatalogProvider {
            private string $rawName = 'Mudkip v1';

            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'mcd21',
                    name: "McDonald's Collection 2021",
                    seriesExternalId: 'mc',
                    seriesName: "McDonald's Collection",
                    printedTotal: 25,
                    total: 25,
                    releaseDate: new DateTimeImmutable('2021-10-22'),
                    logoUrl: 'https://assets.example.test/mcd21/logo',
                    symbolUrl: null,
                    cardExternalIds: ['mcd21-19'],
                    rawData: ['id' => 'mcd21'],
                );
            }

            public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
            {
                yield new ImportedCatalogCard(
                    language: $set->language,
                    externalId: 'mcd21-19',
                    setExternalId: $set->externalId,
                    name: 'Mudkip',
                    number: '19/25',
                    totalNumberInSet: '25',
                    rarity: 'Promo',
                    cardType: 'Pokemon',
                    primaryCollectible: 'Mudkip',
                    illustrator: 'Yuka Morii',
                    imageUrl: 'https://assets.example.test/mcd21/19',
                    thumbnailUrl: null,
                    releaseDate: null,
                    variants: [VariantCode::Holo, VariantCode::Promo],
                    metadata: ['source' => 'tcgdex', 'imagePolicy' => 'external_url_only'],
                    rawData: ['name' => $this->rawName],
                );
            }

            public function findCard(string $language, string $externalId): ?ImportedCatalogCard
            {
                return null;
            }

            public function refreshWith(string $rawName): void
            {
                $this->rawName = $rawName;
            }
        };
        $importer = new ImportCatalog($provider, new DoctrineCatalogImportStore($this->entityManager));

        // Act
        $firstImport = $importer->execute(new ImportCatalogRequest(['en'], batchSize: 1));
        /** @var CardExternalReference $reference */
        $reference = $this->entityManager->getRepository(CardExternalReference::class)->findOneBy([
            'sourceName' => 'tcgdex',
            'externalId' => 'en:mcd21-19',
        ]);
        $reference->applyManualCorrection(['name' => 'Mudkip corrected']);
        $this->entityManager->flush();
        $provider->refreshWith('Mudkip v2');
        $secondImport = $importer->execute(new ImportCatalogRequest(['en'], batchSize: 1));

        // Assert
        self::assertSame(1, $firstImport->getCreatedSets());
        self::assertSame(1, $firstImport->getCreatedCards());
        self::assertSame(0, $secondImport->getCreatedSets());
        self::assertSame(1, $secondImport->getExistingSets());
        self::assertSame(0, $secondImport->getCreatedCards());
        self::assertSame(1, $secondImport->getSyncedCards());
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM catalog_cards'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_external_references'));

        $this->entityManager->clear();
        /** @var CardExternalReference $syncedReference */
        $syncedReference = $this->entityManager->getRepository(CardExternalReference::class)->findOneBy([
            'sourceName' => 'tcgdex',
            'externalId' => 'en:mcd21-19',
        ]);
        self::assertSame(['name' => 'Mudkip v2'], $syncedReference->getRawData());
        self::assertSame(['name' => 'Mudkip corrected'], $syncedReference->getCorrectedData());
        self::assertNotNull($syncedReference->getLastSyncedAt());
    }

    public function testItUsesOneSeriesForSourceKeysThatOnlyDifferByCase(): void
    {
        // Arrange
        $provider = new class implements CardCatalogProvider {
            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'xy-'.$language,
                    name: 'XY '.$language,
                    seriesExternalId: $language === 'en' ? 'xy' : 'XY',
                    seriesName: 'XY',
                    printedTotal: 0,
                    total: 0,
                    releaseDate: null,
                    logoUrl: null,
                    symbolUrl: null,
                    cardExternalIds: [],
                    rawData: ['cards' => []],
                );
            }

            public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
            {
                return [];
            }

            public function findCard(string $language, string $externalId): ?ImportedCatalogCard
            {
                return null;
            }
        };
        $importer = new ImportCatalog($provider, new DoctrineCatalogImportStore($this->entityManager));

        // Act
        $importer->execute(new ImportCatalogRequest(['en']));
        $importer->execute(new ImportCatalogRequest(['ja']));

        // Assert
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(2, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
    }

    public function testItRefreshesExistingSetMetadataAndMarksFutureSetsAsAnnounced(): void
    {
        // Arrange
        $provider = new class implements CardCatalogProvider {
            public string $name = 'Initial Set';
            public DateTimeImmutable $releaseDate;

            public function __construct()
            {
                $this->releaseDate = new DateTimeImmutable('2020-01-01');
            }

            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'refresh-set',
                    name: $this->name,
                    seriesExternalId: 'refresh-series',
                    seriesName: 'Refresh series',
                    printedTotal: 1,
                    total: 1,
                    releaseDate: $this->releaseDate,
                    logoUrl: 'https://assets.example.test/refresh/logo.webp',
                    symbolUrl: null,
                    cardExternalIds: [],
                    rawData: ['cards' => []],
                );
            }

            public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
            {
                return [];
            }

            public function findCard(string $language, string $externalId): ?ImportedCatalogCard
            {
                return null;
            }
        };
        $importer = new ImportCatalog($provider, new DoctrineCatalogImportStore($this->entityManager));

        // Act
        $importer->execute(new ImportCatalogRequest(['en']));
        $provider->name = 'Updated Future Set';
        $provider->releaseDate = new DateTimeImmutable('2100-03-04');
        $importer->execute(new ImportCatalogRequest(['en']));
        $this->entityManager->clear();
        $series = $this->entityManager->getRepository(CardSeries::class)->findOneBy(['name' => 'Refresh series']);
        self::assertInstanceOf(CardSeries::class, $series);
        $cardSet = $this->entityManager->getRepository(CardSet::class)->findOneBy([
            'series' => $series,
        ]);

        // Assert
        self::assertInstanceOf(CardSet::class, $cardSet);
        self::assertSame('Updated Future Set', $cardSet->getName());
        self::assertSame('2100-03-04', $cardSet->getReleaseDate()?->format('Y-m-d'));
        self::assertSame(CardSetStatus::Announced, $cardSet->getStatus());
    }

    public function testDetailedImportEnrichesACardAndLaterSummaryRefreshKeepsTheDetails(): void
    {
        // Arrange
        $provider = new class implements CardCatalogProvider {
            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'detail-set',
                    name: 'Detail set',
                    seriesExternalId: 'detail-series',
                    seriesName: 'Detail series',
                    printedTotal: 1,
                    total: 1,
                    releaseDate: null,
                    logoUrl: null,
                    symbolUrl: null,
                    cardExternalIds: ['detail-set-1'],
                    rawData: ['cards' => []],
                );
            }

            public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
            {
                yield new ImportedCatalogCard(
                    language: $set->language,
                    externalId: 'detail-set-1',
                    setExternalId: $set->externalId,
                    name: 'Mudkip',
                    number: '1/1',
                    totalNumberInSet: '1',
                    rarity: $includeCardDetails ? 'Rare Holo' : 'Unknown',
                    cardType: $includeCardDetails ? 'Pokemon' : 'Unknown',
                    primaryCollectible: $includeCardDetails ? 'Mudkip' : null,
                    illustrator: $includeCardDetails ? 'Yuka Morii' : null,
                    imageUrl: $includeCardDetails
                        ? 'https://assets.example.test/detail/1'
                        : 'https://assets.example.test/detail/1-summary',
                    thumbnailUrl: null,
                    releaseDate: null,
                    variants: $includeCardDetails ? [VariantCode::Normal, VariantCode::Holo] : [VariantCode::Normal],
                    metadata: ['sourceDetailLevel' => $includeCardDetails ? 'detail' : 'summary'],
                    rawData: $includeCardDetails
                        ? ['category' => 'Pokemon', 'variants' => ['holo' => true]]
                        : ['id' => 'detail-set-1'],
                    isDetailed: $includeCardDetails,
                );
            }

            public function findCard(string $language, string $externalId): ?ImportedCatalogCard
            {
                return null;
            }
        };
        $importer = new ImportCatalog($provider, new DoctrineCatalogImportStore($this->entityManager));

        // Act
        $importer->execute(new ImportCatalogRequest(['en']));
        $importer->execute(new ImportCatalogRequest(['en'], includeCardDetails: true));
        $importer->execute(new ImportCatalogRequest(['en']));
        $this->entityManager->clear();
        /** @var CatalogCard $card */
        $card = $this->entityManager->getRepository(CatalogCard::class)->findOneBy(['number' => '1/1']);

        // Assert
        self::assertSame('Rare Holo', $card->getRarity());
        self::assertSame('Pokemon', $card->getCardType());
        self::assertSame('Yuka Morii', $card->getIllustrator());
        self::assertSame('https://assets.example.test/detail/1-summary', $card->getImageUrl());
        self::assertSame('detail', $card->getMetadata()['sourceDetailLevel']);
        self::assertSame(['normal', 'holo'], array_map(
            static fn (\App\Catalog\Domain\Entity\CardVariant $variant): string => $variant->getCode()->value,
            $card->getVariants()->toArray(),
        ));
    }

    public function testItReusesAnExistingManualSeriesWithTheSameName(): void
    {
        // Arrange
        $existingSeries = new CardSeries('Existing series');
        $this->entityManager->persist($existingSeries);
        $this->entityManager->flush();
        $provider = new class implements CardCatalogProvider {
            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'existing-series-set',
                    name: 'Imported set',
                    seriesExternalId: 'existing-series',
                    seriesName: 'Existing series',
                    printedTotal: 0,
                    total: 0,
                    releaseDate: null,
                    logoUrl: null,
                    symbolUrl: null,
                    cardExternalIds: [],
                    rawData: ['cards' => []],
                );
            }

            public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
            {
                return [];
            }

            public function findCard(string $language, string $externalId): ?ImportedCatalogCard
            {
                return null;
            }
        };
        $importer = new ImportCatalog($provider, new DoctrineCatalogImportStore($this->entityManager));

        // Act
        $importer->execute(new ImportCatalogRequest(['en']));

        // Assert
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
    }
}
