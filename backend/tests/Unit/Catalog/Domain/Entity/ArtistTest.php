<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\Artist;
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
}
