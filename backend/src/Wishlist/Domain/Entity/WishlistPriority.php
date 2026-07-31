<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Entity;

enum WishlistPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Wanted = 'wanted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
