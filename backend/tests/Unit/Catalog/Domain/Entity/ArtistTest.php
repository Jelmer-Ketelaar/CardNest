<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\Artist;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArtistTest extends TestCase
{
    public function testItNormalizesAnArtistNameWithoutChangingItsDisplayName(): void
    {
        // Arrange
        $name = '  Mitsuhiro   Arita  ';

        // Act
        $artist = Artist::fromCanonicalName($name);

        // Assert
        self::assertSame('Mitsuhiro Arita', $artist->getCanonicalName());
        self::assertSame('mitsuhiro arita', $artist->getNormalizedName());
        self::assertSame('mitsuhiro-arita', $artist->getSlug());
    }

    public function testItRecordsSourceReferencesByLanguageWithoutDiscardingExistingOnes(): void
    {
        // Arrange
        $artist = Artist::fromCanonicalName('Mitsuhiro Arita');
        $firstSync = new DateTimeImmutable('2026-08-03T10:00:00+00:00');
        $secondSync = new DateTimeImmutable('2026-08-03T11:00:00+00:00');

        // Act
        $artist->recordExternalReference('tcgdex', 'ja', 'Mitsuhiro Arita', $firstSync);
        $artist->recordExternalReference('tcgdex', 'en', 'Mitsuhiro Arita', $secondSync);

        // Assert
        self::assertSame([
            'tcgdex' => [
                'referencesByLanguage' => [
                    'en' => 'Mitsuhiro Arita',
                    'ja' => 'Mitsuhiro Arita',
                ],
                'lastSyncedAt' => $secondSync->format(DATE_ATOM),
            ],
        ], $artist->getExternalReferences());
        self::assertSame($secondSync, $artist->getUpdatedAt());
    }

    public function testItRejectsAnEmptyArtistName(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('canonical name is required');

        // Act
        Artist::fromCanonicalName('   ');

        // Assert
        // The expected exception prevents an unusable canonical record.
    }
}
