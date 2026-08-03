<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Dto\ImportCatalogArtistsRequest;
use App\Catalog\Application\ImportCatalogArtists;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Provider\CatalogArtistProvider;
use App\Catalog\Domain\Provider\ImportedArtistCardLinks;
use App\Catalog\Infrastructure\Import\DoctrineCatalogArtistImportStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportCatalogArtistsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testItBackfillsAndReconcilesSourceArtistsIdempotently(): void
    {
        // Arrange
        $series = new CardSeries('Artist import series');
        $set = new CardSet(
            series: $series,
            name: 'Artist import set',
            code: 'artist-import-en',
            language: 'en',
            printedTotal: 3,
            total: 3,
            status: CardSetStatus::Active,
        );
        $sourceCard = new CatalogCard($set, 'Source card', '1/3', 'Rare', 'Pokemon');
        $sourceCard->addExternalReference('tcgdex', 'en:artist-import-1');
        $legacyCard = new CatalogCard(
            $set,
            'Legacy card',
            '2/3',
            'Rare',
            'Pokemon',
            illustrator: 'Legacy Artist',
        );
        $legacyCard->addExternalReference('tcgdex', 'en:artist-import-2');
        $conflictingCard = new CatalogCard(
            $set,
            'Conflicting card',
            '3/3',
            'Rare',
            'Pokemon',
            illustrator: 'Stored Artist',
        );
        $conflictingCard->addExternalReference('tcgdex', 'en:artist-import-3');
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($sourceCard);
        $this->entityManager->persist($legacyCard);
        $this->entityManager->persist($conflictingCard);
        $this->entityManager->flush();

        $provider = new class implements CatalogArtistProvider {
            public function getProviderName(): string
            {
                return 'tcgdex';
            }

            public function importArtistCardLinks(string $language, array $onlyArtistNames = []): iterable
            {
                yield new ImportedArtistCardLinks(
                    $language,
                    'Source Artist',
                    'Source Artist',
                    ['artist-import-1', 'missing-card'],
                );
                yield new ImportedArtistCardLinks(
                    $language,
                    'Different Source Artist',
                    'Different Source Artist',
                    ['artist-import-3'],
                );
            }
        };
        $importer = new ImportCatalogArtists(
            $provider,
            new DoctrineCatalogArtistImportStore($this->entityManager),
        );

        // Act
        $firstResult = $importer->execute(new ImportCatalogArtistsRequest(['en'], batchSize: 1));
        $secondResult = $importer->execute(new ImportCatalogArtistsRequest(['en'], batchSize: 1));
        $this->entityManager->clear();
        $reloadedSourceCard = $this->entityManager->find(CatalogCard::class, $sourceCard->getId());
        $reloadedLegacyCard = $this->entityManager->find(CatalogCard::class, $legacyCard->getId());
        $sourceArtist = $this->entityManager->getRepository(Artist::class)->findOneBy([
            'normalizedName' => 'source artist',
        ]);

        // Assert
        self::assertSame(4, $firstResult->getCreatedArtists());
        self::assertSame(2, $firstResult->getProcessedArtists());
        self::assertSame(1, $firstResult->getLinkedCards());
        self::assertSame(2, $firstResult->getBackfilledCards());
        self::assertSame(1, $firstResult->getMissingCards());
        self::assertSame(1, $firstResult->getConflictingCards());
        self::assertSame(0, $secondResult->getCreatedArtists());
        self::assertSame(2, $secondResult->getExistingArtists());
        self::assertSame(1, $secondResult->getExistingCardLinks());
        self::assertSame(0, $secondResult->getBackfilledCards());
        self::assertSame(4, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM artists'));
        self::assertSame(3, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM catalog_card_artists'));
        self::assertInstanceOf(CatalogCard::class, $reloadedSourceCard);
        self::assertInstanceOf(CatalogCard::class, $reloadedLegacyCard);
        self::assertSame('Source Artist', $reloadedSourceCard->getIllustrator());
        self::assertSame('Legacy Artist', $reloadedLegacyCard->getIllustrator());
        self::assertInstanceOf(Artist::class, $sourceArtist);
        self::assertSame(
            'Source Artist',
            $sourceArtist->getExternalReferences()['tcgdex']['referencesByLanguage']['en'] ?? null,
        );
    }
}
