<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

/**
 * Languages exposed by the configured catalog provider. Collection language
 * values keep a small number of legacy values in addition to this list so
 * existing user data remains valid.
 */
enum CatalogLanguage: string
{
    case English = 'en';
    case French = 'fr';
    case Spanish = 'es';
    case SpanishMexico = 'es-mx';
    case Italian = 'it';
    case Portuguese = 'pt';
    case PortugueseBrazil = 'pt-br';
    case PortuguesePortugal = 'pt-pt';
    case German = 'de';
    case Dutch = 'nl';
    case Polish = 'pl';
    case Russian = 'ru';
    case Japanese = 'ja';
    case Korean = 'ko';
    case ChineseTraditional = 'zh-tw';
    case Indonesian = 'id';
    case Thai = 'th';
    case ChineseSimplified = 'zh-cn';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
