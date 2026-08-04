<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CatalogDiscrepancyEntityType: string
{
    case Import = 'import';
    case Scope = 'scope';
    case Set = 'set';
    case Card = 'card';
    case Pokemon = 'pokemon';
    case Artist = 'artist';
    case Image = 'image';
    case Price = 'price';
}
