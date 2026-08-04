<?php

declare(strict_types=1);

namespace App\Catalog\Application\Coverage;

use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;

interface CatalogCoverageDataSource
{
    public function measure(CatalogCoverageManifest $manifest): CatalogCoverageMeasurement;
}
