<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifestSet;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CatalogCoverageManifestTest extends TestCase
{
    public function testChecksumIsStableWhenSetOrderChanges(): void
    {
        // Arrange
        $first = $this->manifest([
            new CatalogCoverageManifestSet('base1', 102),
            new CatalogCoverageManifestSet('jungle', 64),
        ]);
        $second = $this->manifest([
            new CatalogCoverageManifestSet('jungle', 64),
            new CatalogCoverageManifestSet('base1', 102),
        ]);

        // Act
        $firstChecksum = $first->checksum();
        $secondChecksum = $second->checksum();

        // Assert
        $this->assertSame($firstChecksum, $secondChecksum);
        $this->assertSame(2, $first->expectedSetCount());
        $this->assertSame(166, $first->expectedCardCount());
    }

    public function testDuplicateSetEvidenceIsRejected(): void
    {
        // Arrange
        $sets = [
            new CatalogCoverageManifestSet('base1', 102),
            new CatalogCoverageManifestSet('base1', 102),
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->manifest($sets);
    }

    /** @param list<CatalogCoverageManifestSet> $sets */
    private function manifest(array $sets): CatalogCoverageManifest
    {
        return new CatalogCoverageManifest(
            provider: 'tcgdex',
            region: 'international',
            language: 'en',
            evidenceSource: 'approved-manifest',
            evidenceVersion: '2026-08-04',
            evidenceReference: 'catalog-evidence://international/en/2026-08-04',
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
            sets: $sets,
        );
    }
}
