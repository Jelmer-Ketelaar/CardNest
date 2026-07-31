<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

enum CollectionItemStatus: string
{
    case InCollection = 'in_collection';
    case ForSale = 'for_sale';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
