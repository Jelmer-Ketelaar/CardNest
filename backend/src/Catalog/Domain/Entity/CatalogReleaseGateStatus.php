<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogReleaseGateStatus: string
{
    case Passed = 'passed';
    case BlockedDataGaps = 'blocked_data_gaps';
    case BlockedDiscrepancies = 'blocked_discrepancies';
    case NotVerified = 'not_verified';
    case BlockedExternal = 'blocked_external';
}
