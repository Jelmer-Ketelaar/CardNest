<?php

declare(strict_types=1);

namespace App\Marketplace\Infrastructure\Persistence;

use App\Marketplace\Domain\Entity\ListingReservation;
use App\Marketplace\Domain\Repository\ListingReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineListingReservationRepository implements ListingReservationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(ListingReservation $reservation): void
    {
        $this->entityManager->persist($reservation);
    }
}
