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
#[ORM\Table(name: 'order_items')]
#[ORM\UniqueConstraint(name: 'uniq_order_item_listing', columns: ['listing_id'])]
final class OrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Order $order,
        #[ORM\ManyToOne(targetEntity: Listing::class)]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Listing $listing,
        #[ORM\Column(name: 'price_amount')]
        private readonly int $priceAmount,
        #[ORM\Column(name: 'price_currency', length: 3)]
        private readonly string $priceCurrency,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public static function fromListing(Order $order, Listing $listing, DateTimeImmutable $createdAt): self
    {
        $price = $listing->getPrice();

        return new self($order, $listing, $price->amount, $price->currency, $createdAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getListing(): Listing
    {
        return $this->listing;
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
