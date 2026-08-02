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
    case SpanishMexico = 'es-mx';
    case Portuguese = 'pt';
    case PortugueseBrazil = 'pt-br';
    case PortuguesePortugal = 'pt-pt';
    case Chinese = 'zh';
    case ChineseTraditional = 'zh-tw';
    case ChineseSimplified = 'zh-cn';
    case Polish = 'pl';
    case Russian = 'ru';
    case Indonesian = 'id';
    case Thai = 'th';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
