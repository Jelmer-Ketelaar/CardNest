<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Exception\InvalidListing;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'listing_reservations')]
#[ORM\Index(name: 'idx_listing_reservations_buyer', columns: ['buyer_id', 'created_at'])]
#[ORM\Index(name: 'idx_listing_reservations_expiration', columns: ['status', 'expires_at'])]
#[ORM\Index(name: 'idx_listing_reservations_checkout', columns: ['checkout_reference'])]
#[ORM\UniqueConstraint(
    name: 'uniq_active_listing_reservation',
    columns: ['listing_id'],
    options: ['where' => "((status)::text = 'active'::text)"],
)]
final class ListingReservation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'released_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $releasedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Listing::class)]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Listing $listing,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'buyer_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $buyer,
        #[ORM\Column(name: 'checkout_reference', type: UuidType::NAME)]
        private readonly Uuid $checkoutReference,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(length: 20, enumType: ListingReservationStatus::class)]
        private ListingReservationStatus $status = ListingReservationStatus::Active,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($listing->getStatus() !== ListingStatus::Reserved) {
            throw new InvalidListing('Alleen een gereserveerde listing kan een reserveringsrecord krijgen.');
        }
        if ($buyer->getId()->equals($listing->getSeller()->getId())) {
            throw new InvalidListing('Een verkoper kan zijn eigen listing niet reserveren.');
        }
        if ($expiresAt <= $createdAt) {
            throw new InvalidListing('De reserveringstijd moet in de toekomst eindigen.');
        }

        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getBuyer(): User
    {
        return $this->buyer;
    }

    public function getCheckoutReference(): Uuid
    {
        return $this->checkoutReference;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getStatus(): ListingReservationStatus
    {
        return $this->status;
    }

    public function complete(DateTimeImmutable $completedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== ListingReservationStatus::Active) {
            throw new InvalidListing('Alleen een actieve reservering kan worden voltooid.');
        }

        $this->status = ListingReservationStatus::Completed;
        $this->completedAt = $completedAt;
    }

    public function release(DateTimeImmutable $releasedAt = new DateTimeImmutable()): void
    {
        if ($this->status !== ListingReservationStatus::Active) {
            return;
        }

        $this->status = ListingReservationStatus::Released;
        $this->releasedAt = $releasedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getReleasedAt(): ?DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
