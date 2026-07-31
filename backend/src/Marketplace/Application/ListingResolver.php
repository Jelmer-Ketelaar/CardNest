<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Exception\ListingNotFound;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Repository\ListingRepository;

final readonly class ListingResolver
{
    public function __construct(private ListingRepository $listings)
    {
    }

    public function active(string $id): Listing
    {
        $listing = $this->listings->getActive($id);
        if (!$listing instanceof Listing) {
            throw new ListingNotFound('Listing niet gevonden.');
        }

        return $listing;
    }

    public function owned(string $id, User $seller): Listing
    {
        $listing = $this->listings->getForSeller($id, $seller);
        if (!$listing instanceof Listing) {
            throw new ListingNotFound('Listing niet gevonden.');
        }

        return $listing;
    }
}
