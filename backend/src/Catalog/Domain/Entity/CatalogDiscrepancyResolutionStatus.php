<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogDiscrepancyResolutionStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Resolved = 'resolved';
    case IgnoredWithReason = 'ignored_with_reason';
    case BlockedExternal = 'blocked_external';

    public function blocksRelease(): bool
    {
        return $this === self::Open || $this === self::BlockedExternal;
    }
}
