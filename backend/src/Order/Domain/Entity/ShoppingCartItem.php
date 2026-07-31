<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Marketplace\Domain\Entity\Listing;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'shopping_cart_items')]
#[ORM\UniqueConstraint(name: 'uniq_cart_listing', columns: ['cart_id', 'listing_id'])]
final class ShoppingCartItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ShoppingCart::class, inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'cart_id', nullable: false, onDelete: 'CASCADE')]
        private readonly ShoppingCart $cart,
        #[ORM\ManyToOne(targetEntity: Listing::class)]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Listing $listing,
        #[ORM\Column(name: 'added_price_amount')]
        private readonly int $addedPriceAmount,
        #[ORM\Column(name: 'added_price_currency', length: 3)]
        private readonly string $addedPriceCurrency,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public static function forListing(ShoppingCart $cart, Listing $listing, DateTimeImmutable $createdAt): self
    {
        $price = $listing->getPrice();

        return new self($cart, $listing, $price->amount, $price->currency, $createdAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    public function getAddedPrice(): Money
    {
        return new Money($this->addedPriceAmount, $this->addedPriceCurrency);
    }

    public function isPriceChanged(): bool
    {
        $current = $this->listing->getPrice();

        return $current->amount !== $this->addedPriceAmount || $current->currency !== $this->addedPriceCurrency;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
