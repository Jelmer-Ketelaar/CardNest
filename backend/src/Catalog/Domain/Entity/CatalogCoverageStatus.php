<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogCoverageStatus: string
{
    case Complete = 'complete';
    case MostlyComplete = 'mostly_complete';
    case PartiallyComplete = 'partially_complete';
    case Missing = 'missing';
    case NotVerified = 'not_verified';
    case BlockedExternal = 'blocked_external';

    public static function assess(?int $observed, ?int $expected, bool $blockedExternal = false): self
    {
        if ($blockedExternal) {
            return self::BlockedExternal;
        }
        if ($observed === null || $expected === null) {
            return self::NotVerified;
        }
        if ($observed === $expected) {
            return self::Complete;
        }
        if ($observed === 0) {
            return self::Missing;
        }
        if ($expected > 0 && $observed < $expected && intdiv($observed * 10_000, $expected) >= 9_500) {
            return self::MostlyComplete;
        }

        return self::PartiallyComplete;
    }
}
