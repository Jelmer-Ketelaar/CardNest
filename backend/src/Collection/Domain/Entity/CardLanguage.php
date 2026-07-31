<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

enum CardLanguage: string
{
    case English = 'en';
    case Dutch = 'nl';
    case Japanese = 'ja';
    case Korean = 'ko';
    case German = 'de';
    case French = 'fr';
    case Italian = 'it';
    case Spanish = 'es';
    case Portuguese = 'pt';
    case Chinese = 'zh';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
