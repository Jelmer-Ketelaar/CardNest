<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

enum ListingImageSide: string
{
    case Front = 'front';
    case Back = 'back';
}
