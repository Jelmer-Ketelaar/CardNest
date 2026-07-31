<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

enum CardSetStatus: string
{
    case Announced = 'announced';
    case Active = 'active';
    case OutOfPrint = 'out_of_print';
    case Unknown = 'unknown';
}
