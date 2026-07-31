<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;

final class CheckoutSellerGroup
{
    /** @var list<Listing> */
    private array $listings = [];

    public function __construct(private readonly User $seller)
    {
    }

    public function add(Listing $listing): void
    {
        $this->listings[] = $listing;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    /** @return list<Listing> */
    public function getListings(): array
    {
        return $this->listings;
    }

    public function itemSubtotal(): int
    {
        return array_sum(array_map(
            static fn (Listing $listing): int => $listing->getPrice()->amount,
            $this->listings,
        ));
    }
}
