<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

enum CardCondition: string
{
    case Mint = 'mint';
    case NearMint = 'near_mint';
    case Excellent = 'excellent';
    case Good = 'good';
    case LightPlayed = 'light_played';
    case Played = 'played';
    case Poor = 'poor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
