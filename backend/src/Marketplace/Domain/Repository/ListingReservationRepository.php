<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Repository;

use App\Marketplace\Domain\Entity\ListingReservation;

interface ListingReservationRepository
{
    public function add(ListingReservation $reservation): void;
}
