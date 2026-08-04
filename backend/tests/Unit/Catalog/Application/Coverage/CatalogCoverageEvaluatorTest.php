<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Coverage;

use App\Catalog\Application\Coverage\CatalogCoverageEvaluator;
use App\Catalog\Application\Coverage\CatalogCoverageMeasurement;
use App\Catalog\Application\Coverage\CatalogSetCoverageMeasurement;
use App\Catalog\Domain\Entity\CatalogCoverageDimension;
use App\Catalog\Domain\Entity\CatalogReleaseGateStatus;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifestSet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CatalogCoverageEvaluatorTest extends TestCase
{
    public function testCompleteVerifiedCoreEvidencePassesReleaseGate(): void
    {
        // Arrange
        $manifest = $this->manifest();
        $measurement = $this->measurement(
            pokemonSpeciesCount: 1,
            pokemonFormCount: 0,
            pokemonMappedCardCount: 1,
        );

        // Act
        $evaluation = (new CatalogCoverageEvaluator())->evaluate($manifest, $measurement, 1, 0, 0);

        // Assert
        $this->assertSame(CatalogReleaseGateStatus::Passed, $evaluation->releaseGateStatus);
        $this->assertSame([], $evaluation->blockingReasons);
        $this->assertSame('complete', $evaluation->dimensions['cards']['status']);
        $this->assertSame(10_000, $evaluation->dimensions['cards']['coverageBasisPoints']);
    }

    public function testUnavailablePokemonEvidenceKeepsGateNotVerifiedAndImagesSeparate(): void
    {
        // Arrange
        $manifest = $this->manifest([CatalogCoverageDimension::Images]);
        $measurement = $this->measurement();

        // Act
        $evaluation = (new CatalogCoverageEvaluator())->evaluate($manifest, $measurement, 1, 0, 0);

        // Assert
        $this->assertSame(CatalogReleaseGateStatus::NotVerified, $evaluation->releaseGateStatus);
        $this->assertSame('not_verified', $evaluation->dimensions['pokemon_species']['status']);
        $this->assertSame('blocked_external', $evaluation->dimensions['images']['status']);
        $this->assertContains('pokemon_species_not_verified', $evaluation->blockingReasons);
        $this->assertNotContains('images_blocked_external', $evaluation->blockingReasons);
    }

    public function testCriticalDiscrepancyBlocksOtherwiseCompleteEvidence(): void
    {
        // Arrange
        $manifest = $this->manifest();
        $measurement = $this->measurement(
            pokemonSpeciesCount: 1,
            pokemonFormCount: 0,
            pokemonMappedCardCount: 1,
        );

        // Act
        $evaluation = (new CatalogCoverageEvaluator())->evaluate($manifest, $measurement, 1, 2, 0);

        // Assert
        $this->assertSame(CatalogReleaseGateStatus::BlockedDiscrepancies, $evaluation->releaseGateStatus);
        $this->assertContains('unresolved_critical_discrepancies', $evaluation->blockingReasons);
    }

    public function testObservedCountAboveExpectationDoesNotReportMoreThanOneHundredPercent(): void
    {
        // Arrange
        $manifest = $this->manifest();
        $measurement = new CatalogCoverageMeasurement(
            sets: [new CatalogSetCoverageMeasurement(
                catalogCode: 'tcgdex:en:base1',
                cardCount: 2,
                stableOrderCardCount: 2,
                officialOrderCardCount: 2,
                imageCardCount: 1,
                artistCardCount: 1,
                pricedCardCount: 1,
            )],
            duplicateSetCount: 0,
            duplicateCardCount: 0,
            orphanCardCount: 0,
            pokemonSpeciesCount: 1,
            pokemonFormCount: 0,
            pokemonMappedCardCount: 1,
        );

        // Act
        $evaluation = (new CatalogCoverageEvaluator())->evaluate($manifest, $measurement, 0, 0, 0);

        // Assert
        $this->assertSame(10_000, $evaluation->dimensions['cards']['coverageBasisPoints']);
        $this->assertSame('partially_complete', $evaluation->dimensions['cards']['status']);
        $this->assertSame(CatalogReleaseGateStatus::BlockedDataGaps, $evaluation->releaseGateStatus);
    }

    /** @param list<CatalogCoverageDimension> $blockedDimensions */
    private function manifest(array $blockedDimensions = []): CatalogCoverageManifest
    {
        return new CatalogCoverageManifest(
            provider: 'tcgdex',
            region: 'international',
            language: 'en',
            evidenceSource: 'approved-manifest',
            evidenceVersion: '2026-08-04',
            evidenceReference: 'catalog-evidence://international/en/2026-08-04',
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
            sets: [new CatalogCoverageManifestSet('base1', 1)],
            expectedPokemonSpeciesCount: 1,
            expectedPokemonFormCount: 0,
            expectedImageCardCount: 1,
            expectedArtistCardCount: 1,
            expectedPokemonMappedCardCount: 1,
            expectedPricedCardCount: 1,
            blockedDimensions: $blockedDimensions,
        );
    }

    private function measurement(
        ?int $pokemonSpeciesCount = null,
        ?int $pokemonFormCount = null,
        ?int $pokemonMappedCardCount = null,
    ): CatalogCoverageMeasurement {
        return new CatalogCoverageMeasurement(
            sets: [new CatalogSetCoverageMeasurement(
                catalogCode: 'tcgdex:en:base1',
                cardCount: 1,
                stableOrderCardCount: 1,
                officialOrderCardCount: 1,
                imageCardCount: 1,
                artistCardCount: 1,
                pricedCardCount: 1,
            )],
            duplicateSetCount: 0,
            duplicateCardCount: 0,
            orphanCardCount: 0,
            pokemonSpeciesCount: $pokemonSpeciesCount,
            pokemonFormCount: $pokemonFormCount,
            pokemonMappedCardCount: $pokemonMappedCardCount,
        );
    }
}
