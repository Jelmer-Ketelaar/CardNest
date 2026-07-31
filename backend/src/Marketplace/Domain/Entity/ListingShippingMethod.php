<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'listing_shipping_methods')]
#[ORM\UniqueConstraint(name: 'uniq_listing_shipping_method', columns: ['listing_id', 'code'])]
final class ListingShippingMethod
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'shippingMethods')]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Listing $listing,
        #[ORM\Column(length: 30, enumType: ShippingMethodCode::class)]
        private readonly ShippingMethodCode $code,
        #[ORM\Column(name: 'price_amount')]
        private readonly int $priceAmount,
        #[ORM\Column(name: 'price_currency', length: 3)]
        private readonly string $priceCurrency,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public static function fromOption(Listing $listing, ShippingOption $option): self
    {
        return new self($listing, $option->code, $option->price->amount, $option->price->currency);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getCode(): ShippingMethodCode
    {
        return $this->code;
    }

    public function getPrice(): Money
    {
        return new Money($this->priceAmount, $this->priceCurrency);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
