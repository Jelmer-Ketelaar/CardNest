<?php

declare(strict_types=1);

namespace App\Catalog\Application\Coverage;

use App\Catalog\Domain\Entity\CatalogReleaseGateStatus;

final readonly class CatalogCoverageEvaluation
{
    /**
     * @param array<string, array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }> $dimensions
     * @param list<string> $blockingReasons
     */
    public function __construct(
        public array $dimensions,
        public CatalogReleaseGateStatus $releaseGateStatus,
        public array $blockingReasons,
    ) {
    }
}
