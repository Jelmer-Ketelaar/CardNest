<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Entity;

enum PriceVariant: string
{
    case Standard = 'standard';
    case Foil = 'foil';
    case Unavailable = 'unavailable';
}
