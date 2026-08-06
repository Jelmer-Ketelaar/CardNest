<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum PokemonFormType: string
{
    case Default = 'default';
    case Regional = 'regional';
    case MegaEvolution = 'mega_evolution';
    case PrimalReversion = 'primal_reversion';
    case Gigantamax = 'gigantamax';
    case OfficialAlternate = 'official_alternate';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
