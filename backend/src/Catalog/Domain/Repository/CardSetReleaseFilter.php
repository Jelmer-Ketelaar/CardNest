<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

enum CardSetReleaseFilter: string
{
    case All = 'all';
    case Upcoming = 'upcoming';
    case Released = 'released';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
