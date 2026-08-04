<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogDiscrepancySeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Informational = 'informational';

    public function rank(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Informational => 1,
        };
    }
}
