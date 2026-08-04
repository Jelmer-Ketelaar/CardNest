<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogImportStatus: string
{
    case Running = 'running';
    case Validating = 'validating';
    case Publishing = 'publishing';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::CompletedWithWarnings, self::Skipped, self::Failed => true,
            self::Running, self::Validating, self::Publishing => false,
        };
    }
}
