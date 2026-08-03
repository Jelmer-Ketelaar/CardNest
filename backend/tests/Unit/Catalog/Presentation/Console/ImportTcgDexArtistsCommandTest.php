<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Presentation\Console;

use App\Catalog\Application\Import\CatalogArtistImportStore;
use App\Catalog\Application\ImportCatalogArtists;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Provider\CatalogArtistProvider;
use App\Catalog\Domain\Provider\ImportedArtistCardLinks;
use App\Catalog\Presentation\Console\ImportTcgDexArtistsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class ImportTcgDexArtistsCommandTest extends TestCase
{
    public function testItRequiresNoDebugForAnAllArtistImport(): void
    {
        // Arrange
        $provider = $this->createStub(CatalogArtistProvider::class);
        $store = $this->createStub(CatalogArtistImportStore::class);
        $store->method('catalogLanguages')->willReturn(['en']);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $command = new ImportTcgDexArtistsCommand(new ImportCatalogArtists($provider, $store), $kernel);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--no-debug', $tester->getDisplay());
    }

    public function testItRejectsAnInvalidBatchSizeBeforeCallingTheProvider(): void
    {
        // Arrange
        $provider = $this->createStub(CatalogArtistProvider::class);
        $store = $this->createStub(CatalogArtistImportStore::class);
        $store->method('catalogLanguages')->willReturn(['en']);
        $kernel = $this->createStub(KernelInterface::class);
        $command = new ImportTcgDexArtistsCommand(new ImportCatalogArtists($provider, $store), $kernel);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['--batch-size' => '0']);

        // Assert
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('between 1 and 500', $tester->getDisplay());
    }

    public function testItReportsOperationalMetadataWithoutInventingAProviderCutoff(): void
    {
        // Arrange
        $provider = new class implements CatalogArtistProvider {
            public function getProviderName(): string
            {
                return 'tcgdex';
            }

            public function importArtistCardLinks(string $language, array $onlyArtistNames = []): iterable
            {
                yield new ImportedArtistCardLinks($language, 'Test Artist', 'Test Artist', []);
            }
        };
        $store = new class implements CatalogArtistImportStore {
            /** @var array<string, Artist> */
            private array $artists = [];

            public function catalogLanguages(): array
            {
                return ['en'];
            }

            public function findArtistByNormalizedName(string $normalizedName): ?Artist
            {
                return $this->artists[$normalizedName] ?? null;
            }

            public function findArtistBySlug(string $slug): ?Artist
            {
                foreach ($this->artists as $artist) {
                    if ($artist->getSlug() === $slug) {
                        return $artist;
                    }
                }

                return null;
            }

            public function findCardsByExternalReferences(string $sourceName, array $externalReferences): array
            {
                return [];
            }

            public function findUnlinkedCardsWithIllustrator(int $limit): array
            {
                return [];
            }

            public function persist(Artist|CatalogCard $entity): void
            {
                if ($entity instanceof Artist) {
                    $this->artists[$entity->getNormalizedName()] = $entity;
                }
            }

            public function flush(): void
            {
            }

            public function clear(): void
            {
            }
        };
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('isDebug')->willReturn(true);
        $command = new ImportTcgDexArtistsCommand(new ImportCatalogArtists($provider, $store), $kernel);
        $tester = new CommandTester($command);

        // Act
        $exitCode = $tester->execute(['--language' => ['en'], '--artist' => ['Test Artist']]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Last source group: en:Test Artist.', $tester->getDisplay());
        self::assertStringContainsString('Provider data cutoff: unavailable', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Duration: \d+\.\d{2} seconds\./', $tester->getDisplay());
    }
}
