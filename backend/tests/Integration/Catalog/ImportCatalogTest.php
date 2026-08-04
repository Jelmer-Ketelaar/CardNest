<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\CatalogDiscrepancyRecorder;
use App\Catalog\Application\Dto\ImportCatalogRequest;
use App\Catalog\Application\Exception\CatalogImportAlreadyRunning;
use App\Catalog\Application\Exception\CatalogImportValidationFailed;
use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Application\Import\CatalogImportValidator;
use App\Catalog\Application\ImportCatalog;
use App\Catalog\Domain\Entity\CardExternalReference;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\CatalogImportStatus;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use App\Catalog\Infrastructure\Import\DoctrineCatalogImportStagingStore;
use App\Catalog\Infrastructure\Import\DoctrineCatalogImportStore;
use App\Catalog\Infrastructure\Persistence\DoctrineCatalogDiscrepancyRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineCatalogImportRunRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

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

            public function getProviderName(): string
            {
                return 'tcgdex';
            }

            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'mcd21',
                    name: "McDonald's Collection 2021",
                    seriesExternalId: 'mc',
                    seriesName: "McDonald's Collection",
                    printedTotal: 1,
                    total: 1,
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
        $importer = $this->importer($provider);

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
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM card_external_references',
        ));

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
            public function getProviderName(): string
            {
                return 'tcgdex';
            }

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
        $importer = $this->importer($provider);

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

            public function getProviderName(): string
            {
                return 'tcgdex';
            }

            public function importSets(string $language, array $onlySetIds = []): iterable
            {
                yield new ImportedCardSet(
                    language: $language,
                    externalId: 'refresh-set',
                    name: $this->name,
                    seriesExternalId: 'refresh-series',
                    seriesName: 'Refresh series',
                    printedTotal: 0,
                    total: 0,
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
        $importer = $this->importer($provider);

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
            public function getProviderName(): string
            {
                return 'tcgdex';
            }

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
        $importer = $this->importer($provider);

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
            public function getProviderName(): string
            {
                return 'tcgdex';
            }

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
        $importer = $this->importer($provider);

        // Act
        $importer->execute(new ImportCatalogRequest(['en']));

        // Assert
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
    }

    public function testItSkipsAnExactReplayOfAnImmutableSourceVersion(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $importer = $this->importer($provider);
        $request = new ImportCatalogRequest(
            languages: ['en'],
            sourceVersion: 'catalog-release-2026-08-03',
            dataCutoff: new DateTimeImmutable('2026-08-03T22:00:00+00:00'),
        );

        // Act
        $first = $importer->execute($request);
        $replay = $importer->execute($request);

        // Assert
        self::assertSame(CatalogImportStatus::Completed, $first->getRunStatus());
        self::assertSame(CatalogImportStatus::Skipped, $replay->getRunStatus());
        self::assertNotSame($first->getRunId(), $replay->getRunId());
        self::assertSame($first->getContentChecksum(), $replay->getContentChecksum());
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame(2, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_import_runs',
        ));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM catalog_import_runs WHERE status = 'skipped' AND duplicate_of_id IS NOT NULL",
        ));
        $this->assertStagingIsEmpty();
    }

    public function testItRejectsChangedContentForTheSameSourceVersionBeforePublishing(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $importer = $this->importer($provider);
        $request = new ImportCatalogRequest(['en'], sourceVersion: 'immutable-release');
        $importer->execute($request);
        $provider->cardName = 'Changed source content';

        // Act
        try {
            $importer->execute($request);
            self::fail('Changed content for an immutable source version must be rejected.');
        } catch (CatalogImportValidationFailed $exception) {
            self::assertStringContainsString('different catalog checksum', $exception->getMessage());
        }

        // Assert
        $this->entityManager->clear();
        $card = $this->entityManager->getRepository(CatalogCard::class)->findOneBy([]);
        self::assertInstanceOf(CatalogCard::class, $card);
        self::assertSame('Mudkip', $card->getName());
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM catalog_import_runs WHERE status = 'failed'",
        ));
        $this->assertStagingIsEmpty();
    }

    public function testProviderFailureLeavesLiveCatalogUntouchedAndRecordsStagedCounts(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $provider->failAfterFirstCard = true;

        // Act
        try {
            $this->importer($provider)->execute(new ImportCatalogRequest(['en']));
            self::fail('A partial provider response must fail the import.');
        } catch (RuntimeException $exception) {
            self::assertSame('Provider failed after returning a partial set.', $exception->getMessage());
        }

        // Assert
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame([
            'status' => CatalogImportStatus::Failed->value,
            'staged_set_count' => 1,
            'staged_card_count' => 1,
            'published_set_count' => 0,
            'published_card_count' => 0,
        ], $this->lastRunCounts());
        $this->assertStagingIsEmpty();
    }

    public function testGenericProviderFailuresAreIdempotentWithoutCollapsingDistinctErrors(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $provider->failAfterFirstCard = true;
        $importer = $this->importer($provider);

        // Act
        foreach (['Repeated provider failure.', 'Repeated provider failure.', 'Different provider failure.'] as $message) {
            $provider->failureMessage = $message;
            try {
                $importer->execute(new ImportCatalogRequest(['en']));
                $this->fail('The configured provider failure must abort the import.');
            } catch (RuntimeException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }

        // Assert
        $occurrenceCounts = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT occurrence_count FROM catalog_discrepancies WHERE discrepancy_type = 'provider_error' ORDER BY occurrence_count",
        );
        $this->assertSame([1, 2], array_map('intval', $occurrenceCounts));
        $this->assertStagingIsEmpty();
    }

    public function testInvalidSourceReferencesAreRejectedBeforePublishing(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $provider->cardExternalIds = ['duplicate-card', 'duplicate-card'];
        $importer = $this->importer($provider);

        // Act
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $importer->execute(new ImportCatalogRequest(['en']));
                $this->fail('Duplicate source card references must fail validation.');
            } catch (CatalogImportValidationFailed $exception) {
                $this->assertStringContainsString('duplicate card references', $exception->getMessage());
            }
        }

        // Assert
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        $this->assertSame(CatalogImportStatus::Failed->value, $this->lastRunCounts()['status']);
        $this->assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM catalog_discrepancies WHERE discrepancy_type = 'duplicate_card'",
        ));
        $this->assertSame(2, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT occurrence_count FROM catalog_discrepancies LIMIT 1',
        ));
        $this->assertStagingIsEmpty();
    }

    public function testItRejectsAnUnboundedNormalizedProviderPayload(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $provider->setRawDataPadding = str_repeat('x', 4_194_304);

        // Act
        try {
            $this->importer($provider)->execute(new ImportCatalogRequest(['en']));
            self::fail('A provider payload above the staging limit must be rejected.');
        } catch (CatalogImportValidationFailed $exception) {
            self::assertStringContainsString('exceeds the 4 MiB import limit', $exception->getMessage());
        }

        // Assert
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(CatalogImportStatus::Failed->value, $this->lastRunCounts()['status']);
        $this->assertStagingIsEmpty();
    }

    public function testItRejectsAConcurrentImportForTheSameProviderScope(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $request = new ImportCatalogRequest(['en']);
        $lockFactory = new LockFactory(new InMemoryStore());
        $heldLock = $lockFactory->createLock($request->lockName($provider->getProviderName()), 3_600.0);
        self::assertTrue($heldLock->acquire());

        // Act
        try {
            try {
                $this->importer($provider, lockFactory: $lockFactory)->execute($request);
                self::fail('A concurrent import for the same scope must be rejected.');
            } catch (CatalogImportAlreadyRunning $exception) {
                self::assertStringContainsString('already running', $exception->getMessage());
            }
        } finally {
            $heldLock->release();
        }

        // Assert
        self::assertSame(CatalogImportStatus::Failed->value, $this->lastRunCounts()['status']);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        $this->assertStagingIsEmpty();
    }

    public function testFailedSetPublicationRollsBackThatEntireSet(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $store = new FailingCatalogImportStore(new DoctrineCatalogImportStore($this->entityManager));

        // Act
        try {
            $this->importer($provider, $store)->execute(new ImportCatalogRequest(['en']));
            self::fail('The injected publication failure must abort the import.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected card publication failure.', $exception->getMessage());
        }

        // Assert
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame([
            'status' => CatalogImportStatus::Failed->value,
            'staged_set_count' => 1,
            'staged_card_count' => 1,
            'published_set_count' => 0,
            'published_card_count' => 0,
        ], $this->lastRunCounts());
        $this->assertStagingIsEmpty();
    }

    public function testProgressFailureRollsBackTheSetInTheSameTransaction(): void
    {
        // Arrange
        $provider = new ConfigurableCatalogProvider();
        $runs = new FailingCatalogImportRunRepository(
            new DoctrineCatalogImportRunRepository($this->entityManager),
        );

        // Act
        try {
            $this->importer($provider, runs: $runs)->execute(new ImportCatalogRequest(['en']));
            self::fail('Publication must fail when its durable progress cannot be recorded.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected publication progress failure.', $exception->getMessage());
        }

        // Assert
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame([
            'status' => CatalogImportStatus::Failed->value,
            'staged_set_count' => 1,
            'staged_card_count' => 1,
            'published_set_count' => 0,
            'published_card_count' => 0,
        ], $this->lastRunCounts());
        $this->assertStagingIsEmpty();
    }

    public function testLaterSetFailureKeepsEarlierCommitAndRecordsExactPartialProgress(): void
    {
        // Arrange
        $provider = new TwoSetCatalogProvider();
        $store = new FailingCatalogImportStore(
            new DoctrineCatalogImportStore($this->entityManager),
            failedCardName: 'Second card',
        );

        // Act
        try {
            $this->importer($provider, $store)->execute(new ImportCatalogRequest(['en']));
            self::fail('The injected second-set failure must abort the import.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected card publication failure.', $exception->getMessage());
        }

        // Assert
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_series'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_cards',
        ));
        self::assertSame([
            'status' => CatalogImportStatus::Failed->value,
            'staged_set_count' => 2,
            'staged_card_count' => 2,
            'published_set_count' => 1,
            'published_card_count' => 1,
        ], $this->lastRunCounts());
        $this->assertStagingIsEmpty();
    }

    private function importer(
        CardCatalogProvider $provider,
        ?CatalogImportStore $store = null,
        ?LockFactory $lockFactory = null,
        ?CatalogImportRunRepository $runs = null,
    ): ImportCatalog {
        return new ImportCatalog(
            $provider,
            $store ?? new DoctrineCatalogImportStore($this->entityManager),
            new DoctrineCatalogImportStagingStore($this->entityManager->getConnection()),
            $runs ?? new DoctrineCatalogImportRunRepository($this->entityManager),
            new CatalogImportValidator(),
            new CatalogDiscrepancyRecorder(new DoctrineCatalogDiscrepancyRepository($this->entityManager)),
            $lockFactory ?? new LockFactory(new InMemoryStore()),
        );
    }

    /**
     * @return array{
     *     status: string,
     *     staged_set_count: int,
     *     staged_card_count: int,
     *     published_set_count: int,
     *     published_card_count: int
     * }
     */
    private function lastRunCounts(): array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT status,
                       staged_set_count,
                       staged_card_count,
                       published_set_count,
                       published_card_count
                FROM catalog_import_runs
                ORDER BY started_at DESC, id DESC
                LIMIT 1
                SQL,
        );
        self::assertIsArray($row);

        return [
            'status' => (string) $row['status'],
            'staged_set_count' => (int) $row['staged_set_count'],
            'staged_card_count' => (int) $row['staged_card_count'],
            'published_set_count' => (int) $row['published_set_count'],
            'published_card_count' => (int) $row['published_card_count'],
        ];
    }

    private function assertStagingIsEmpty(): void
    {
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_import_staged_sets',
        ));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM catalog_import_staged_cards',
        ));
    }
}
