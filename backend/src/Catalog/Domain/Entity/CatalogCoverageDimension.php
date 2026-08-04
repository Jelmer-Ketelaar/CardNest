<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogCoverageDimension: string
{
    case Sets = 'sets';
    case SetCompleteness = 'set_completeness';
    case Cards = 'cards';
    case PokemonSpecies = 'pokemon_species';
    case PokemonForms = 'pokemon_forms';
    case Ordering = 'ordering';
    case Images = 'images';
    case Artists = 'artists';
    case PokemonMapping = 'pokemon_mapping';
    case Prices = 'prices';
}
