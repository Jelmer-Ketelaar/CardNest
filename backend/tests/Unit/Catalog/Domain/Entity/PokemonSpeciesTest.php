<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\PokemonSpecies;
use App\Catalog\Domain\Entity\PokemonSpeciesAliasType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PokemonSpeciesTest extends TestCase
{
    public function testItCreatesACanonicalSpeciesIdentity(): void
    {
        // Arrange
        $introducedAt = new DateTimeImmutable('1996-02-27');
        $createdAt = new DateTimeImmutable('2026-08-04T01:00:00+00:00');

        // Act
        $species = new PokemonSpecies(25, '  Pikachu  ', 1, $introducedAt, $createdAt);

        // Assert
        $this->assertSame(25, $species->getNationalDexNumber());
        $this->assertSame('Pikachu', $species->getCanonicalName());
        $this->assertSame('pikachu', $species->getNormalizedName());
        $this->assertSame('pikachu', $species->getSlug());
        $this->assertSame(1, $species->getGeneration());
        $this->assertSame($introducedAt, $species->getIntroducedAt());
        $this->assertSame($createdAt, $species->getCreatedAt());
        $this->assertSame($createdAt, $species->getUpdatedAt());
    }

    #[DataProvider('provideInvalidIdentity')]
    public function testItRejectsInvalidNumericIdentity(
        int $nationalDexNumber,
        int $generation,
        string $expectedMessage,
    ): void {
        // Arrange
        $exception = null;

        // Act
        try {
            new PokemonSpecies($nationalDexNumber, 'Pikachu', $generation);
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function provideInvalidIdentity(): iterable
    {
        yield 'invalid National Dex number' => [0, 1, 'National Dex number'];
        yield 'invalid generation' => [25, 0, 'generation'];
    }

    public function testItAddsAliasesIdempotentlyAndKeepsTheirProvenance(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-08-04T01:00:00+00:00');
        $aliasRecordedAt = new DateTimeImmutable('2026-08-04T02:00:00+00:00');
        $species = new PokemonSpecies(258, 'Mudkip', 3, createdAt: $createdAt);

        // Act
        $firstAlias = $species->addAlias(
            name: 'Mizugorou',
            type: PokemonSpeciesAliasType::Transliteration,
            source: 'TCGDEX',
            language: 'JA',
            sourceReference: 'pokemon:258:ja',
            recordedAt: $aliasRecordedAt,
        );
        $secondAlias = $species->addAlias(
            name: '  MIZUGOROU ',
            type: PokemonSpeciesAliasType::Transliteration,
            source: 'tcgdex',
            language: 'ja',
        );

        // Assert
        $this->assertSame($firstAlias, $secondAlias);
        $this->assertCount(1, $species->getAliases());
        $this->assertSame('Mizugorou', $firstAlias->getCanonicalName());
        $this->assertSame('mizugorou', $firstAlias->getNormalizedName());
        $this->assertSame('ja', $firstAlias->getLanguage());
        $this->assertSame('tcgdex', $firstAlias->getSource());
        $this->assertSame('pokemon:258:ja', $firstAlias->getSourceReference());
        $this->assertSame($aliasRecordedAt, $species->getUpdatedAt());
    }

    public function testItRejectsAnAliasMatchingTheCanonicalName(): void
    {
        // Arrange
        $species = new PokemonSpecies(25, 'Pikachu', 1);
        $exception = null;

        // Act
        try {
            $species->addAlias(
                name: ' PIKACHU ',
                type: PokemonSpeciesAliasType::AlternateSpelling,
                source: 'manual',
            );
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('must differ', $exception->getMessage());
        $this->assertCount(0, $species->getAliases());
    }

    public function testItRecordsExternalReferencesIdempotentlyAndAdvancesSourceVersion(): void
    {
        // Arrange
        $firstSync = new DateTimeImmutable('2026-08-04T01:00:00+00:00');
        $secondSync = new DateTimeImmutable('2026-08-04T02:00:00+00:00');
        $species = new PokemonSpecies(25, 'Pikachu', 1, createdAt: $firstSync);

        // Act
        $firstReference = $species->recordExternalReference('TCGDEX', ' pokemon:25 ', 'v1', $firstSync);
        $secondReference = $species->recordExternalReference('tcgdex', 'pokemon:25', 'v2', $secondSync);

        // Assert
        $this->assertSame($firstReference, $secondReference);
        $this->assertCount(1, $species->getExternalReferences());
        $this->assertSame('tcgdex', $firstReference->getProvider());
        $this->assertSame('pokemon:25', $firstReference->getExternalId());
        $this->assertSame('v2', $firstReference->getSourceVersion());
        $this->assertSame($firstSync, $firstReference->getImportedAt());
        $this->assertSame($secondSync, $firstReference->getLastSyncedAt());
        $this->assertSame($secondSync, $species->getUpdatedAt());
    }

    public function testItRejectsAnOutOfOrderSourceSync(): void
    {
        // Arrange
        $importedAt = new DateTimeImmutable('2026-08-04T01:00:00+00:00');
        $species = new PokemonSpecies(25, 'Pikachu', 1, createdAt: $importedAt);
        $reference = $species->recordExternalReference('tcgdex', 'pokemon:25', 'v1', $importedAt);
        $reference->markSynced('v3', new DateTimeImmutable('2026-08-04T03:00:00+00:00'));
        $exception = null;

        // Act
        try {
            $reference->markSynced('v2', new DateTimeImmutable('2026-08-04T02:00:00+00:00'));
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('latest synchronization', $exception->getMessage());
        $this->assertSame('v3', $reference->getSourceVersion());
    }
}
