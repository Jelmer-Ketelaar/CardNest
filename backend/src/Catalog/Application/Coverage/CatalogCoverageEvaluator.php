<?php

declare(strict_types=1);

namespace App\Catalog\Application\Coverage;

use App\Catalog\Domain\Entity\CatalogCoverageDimension;
use App\Catalog\Domain\Entity\CatalogCoverageStatus;
use App\Catalog\Domain\Entity\CatalogReleaseGateStatus;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;

final readonly class CatalogCoverageEvaluator
{
    /** @var list<CatalogCoverageDimension> */
    private const array RELEASE_DIMENSIONS = [
        CatalogCoverageDimension::Sets,
        CatalogCoverageDimension::SetCompleteness,
        CatalogCoverageDimension::Cards,
        CatalogCoverageDimension::PokemonSpecies,
        CatalogCoverageDimension::PokemonForms,
        CatalogCoverageDimension::Ordering,
        CatalogCoverageDimension::Artists,
        CatalogCoverageDimension::PokemonMapping,
    ];

    public function evaluate(
        CatalogCoverageManifest $manifest,
        CatalogCoverageMeasurement $measurement,
        int $completeSetCount,
        int $unresolvedCriticalDiscrepancyCount,
        int $externallyBlockedCriticalDiscrepancyCount,
    ): CatalogCoverageEvaluation {
        $dimensions = [
            CatalogCoverageDimension::Sets->value => $this->dimension(
                $measurement->importedSetCount(),
                $manifest->expectedSetCount(),
                $manifest->isBlocked(CatalogCoverageDimension::Sets),
            ),
            CatalogCoverageDimension::SetCompleteness->value => $this->dimension(
                $completeSetCount,
                $manifest->expectedSetCount(),
                $manifest->isBlocked(CatalogCoverageDimension::SetCompleteness),
            ),
            CatalogCoverageDimension::Cards->value => $this->dimension(
                $measurement->importedCardCount(),
                $manifest->expectedCardCount(),
                $manifest->isBlocked(CatalogCoverageDimension::Cards),
            ),
            CatalogCoverageDimension::PokemonSpecies->value => $this->dimension(
                $measurement->pokemonSpeciesCount,
                $manifest->expectedPokemonSpeciesCount,
                $manifest->isBlocked(CatalogCoverageDimension::PokemonSpecies),
            ),
            CatalogCoverageDimension::PokemonForms->value => $this->dimension(
                $measurement->pokemonFormCount,
                $manifest->expectedPokemonFormCount,
                $manifest->isBlocked(CatalogCoverageDimension::PokemonForms),
            ),
            CatalogCoverageDimension::Ordering->value => $this->dimension(
                $measurement->officialOrderCardCount(),
                $manifest->expectedCardCount(),
                $manifest->isBlocked(CatalogCoverageDimension::Ordering),
            ),
            CatalogCoverageDimension::Images->value => $this->dimension(
                $measurement->imageCardCount(),
                $manifest->expectedImageCardCount,
                $manifest->isBlocked(CatalogCoverageDimension::Images),
            ),
            CatalogCoverageDimension::Artists->value => $this->dimension(
                $measurement->artistCardCount(),
                $manifest->expectedArtistCardCount,
                $manifest->isBlocked(CatalogCoverageDimension::Artists),
            ),
            CatalogCoverageDimension::PokemonMapping->value => $this->dimension(
                $measurement->pokemonMappedCardCount,
                $manifest->expectedPokemonMappedCardCount,
                $manifest->isBlocked(CatalogCoverageDimension::PokemonMapping),
            ),
            CatalogCoverageDimension::Prices->value => $this->dimension(
                $measurement->pricedCardCount(),
                $manifest->expectedPricedCardCount,
                $manifest->isBlocked(CatalogCoverageDimension::Prices),
            ),
        ];

        $blockingReasons = $this->blockingReasons(
            $dimensions,
            $measurement,
            $unresolvedCriticalDiscrepancyCount,
            $externallyBlockedCriticalDiscrepancyCount,
        );
        $releaseGateStatus = $this->releaseGateStatus(
            $dimensions,
            $measurement,
            $unresolvedCriticalDiscrepancyCount,
            $externallyBlockedCriticalDiscrepancyCount,
        );

        return new CatalogCoverageEvaluation($dimensions, $releaseGateStatus, $blockingReasons);
    }

    /**
     * @return array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }
     */
    private function dimension(?int $observed, ?int $expected, bool $blockedExternal): array
    {
        $basisPoints = null;
        if ($observed !== null && $expected !== null) {
            $basisPoints = $expected === 0
                ? ($observed === 0 ? 10_000 : 0)
                : min(10_000, intdiv($observed * 10_000, $expected));
        }

        return [
            'expected' => $expected,
            'observed' => $observed,
            'coverageBasisPoints' => $basisPoints,
            'status' => CatalogCoverageStatus::assess($observed, $expected, $blockedExternal)->value,
        ];
    }

    /**
     * @param array<string, array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }> $dimensions
     *
     * @return list<string>
     */
    private function blockingReasons(
        array $dimensions,
        CatalogCoverageMeasurement $measurement,
        int $unresolvedCriticalDiscrepancyCount,
        int $externallyBlockedCriticalDiscrepancyCount,
    ): array {
        $reasons = [];
        if ($unresolvedCriticalDiscrepancyCount > $externallyBlockedCriticalDiscrepancyCount) {
            $reasons[] = 'unresolved_critical_discrepancies';
        }
        if ($externallyBlockedCriticalDiscrepancyCount > 0) {
            $reasons[] = 'externally_blocked_critical_discrepancies';
        }
        if ($measurement->duplicateSetCount > 0) {
            $reasons[] = 'duplicate_canonical_sets';
        }
        if ($measurement->duplicateCardCount > 0) {
            $reasons[] = 'duplicate_canonical_cards';
        }
        if ($measurement->orphanCardCount > 0) {
            $reasons[] = 'orphan_cards';
        }

        foreach (self::RELEASE_DIMENSIONS as $dimension) {
            $status = $dimensions[$dimension->value]['status'];
            if ($status !== CatalogCoverageStatus::Complete->value) {
                $reasons[] = sprintf('%s_%s', $dimension->value, $status);
            }
        }

        return $reasons;
    }

    /**
     * @param array<string, array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }> $dimensions
     */
    private function releaseGateStatus(
        array $dimensions,
        CatalogCoverageMeasurement $measurement,
        int $unresolvedCriticalDiscrepancyCount,
        int $externallyBlockedCriticalDiscrepancyCount,
    ): CatalogReleaseGateStatus {
        if ($unresolvedCriticalDiscrepancyCount > $externallyBlockedCriticalDiscrepancyCount) {
            return CatalogReleaseGateStatus::BlockedDiscrepancies;
        }
        if ($externallyBlockedCriticalDiscrepancyCount > 0) {
            return CatalogReleaseGateStatus::BlockedExternal;
        }

        $releaseStatuses = array_map(
            static fn (CatalogCoverageDimension $dimension): string => $dimensions[$dimension->value]['status'],
            self::RELEASE_DIMENSIONS,
        );
        if (in_array(CatalogCoverageStatus::BlockedExternal->value, $releaseStatuses, true)) {
            return CatalogReleaseGateStatus::BlockedExternal;
        }
        if (in_array(CatalogCoverageStatus::NotVerified->value, $releaseStatuses, true)) {
            return CatalogReleaseGateStatus::NotVerified;
        }
        if (
            $measurement->duplicateSetCount > 0
            || $measurement->duplicateCardCount > 0
            || $measurement->orphanCardCount > 0
            || array_any(
                $releaseStatuses,
                static fn (string $status): bool => $status !== CatalogCoverageStatus::Complete->value,
            )
        ) {
            return CatalogReleaseGateStatus::BlockedDataGaps;
        }

        return CatalogReleaseGateStatus::Passed;
    }
}
