<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

use App\Marketplace\Domain\Exception\InvalidListing;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'listing_images')]
#[ORM\UniqueConstraint(name: 'uniq_listing_image_side', columns: ['listing_id', 'side'])]
final class ListingImage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'images')]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Listing $listing,
        #[ORM\Column(length: 10, enumType: ListingImageSide::class)]
        private readonly ListingImageSide $side,
        #[ORM\Column(length: 2048)]
        private string $url,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public static function fromReference(Listing $listing, ListingPhotoReference $photo): self
    {
        return new self($listing, $photo->side, $photo->url);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getSide(): ListingImageSide
    {
        return $this->side;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function replaceReference(ListingPhotoReference $photo): void
    {
        if ($photo->side !== $this->side) {
            throw new InvalidListing('De vervangende listingfoto moet dezelfde zijde hebben.');
        }

        $this->url = $photo->url;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
