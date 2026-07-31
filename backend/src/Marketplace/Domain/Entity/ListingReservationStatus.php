<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

enum ListingReservationStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Released = 'released';
    case Expired = 'expired';
}
