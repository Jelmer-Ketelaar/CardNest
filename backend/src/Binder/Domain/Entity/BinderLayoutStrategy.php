<?php

declare(strict_types=1);

namespace App\Binder\Domain\Entity;

enum BinderLayoutStrategy: string
{
    case SetNumber = 'set_number';
    case PokedexNumber = 'pokedex_number';
    case Alphabetical = 'alphabetical';
    case CollectibleType = 'collectible_type';
    case Rarity = 'rarity';
    case Value = 'value';
    case PurchaseDate = 'purchase_date';
    case Custom = 'custom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
