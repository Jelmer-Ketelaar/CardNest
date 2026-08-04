<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum PokemonSpeciesAliasType: string
{
    case LocalizedName = 'localized_name';
    case AlternateSpelling = 'alternate_spelling';
    case Transliteration = 'transliteration';
    case FormerOfficialName = 'former_official_name';
}
