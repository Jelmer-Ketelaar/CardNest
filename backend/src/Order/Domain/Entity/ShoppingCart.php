<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Order\Domain\Exception\CartItemNotFound;
use App\Order\Domain\Exception\InvalidCart;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'shopping_carts')]
#[ORM\UniqueConstraint(name: 'uniq_shopping_cart_buyer', columns: ['buyer_id'])]
final class ShoppingCart
{
    private const MAXIMUM_ITEMS = 100;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, ShoppingCartItem> */
    #[ORM\OneToMany(
        mappedBy: 'cart',
        targetEntity: ShoppingCartItem::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $items;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'buyer_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $buyer,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->items = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBuyer(): User
    {
        return $this->buyer;
    }

    /** @return Collection<int, ShoppingCartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function add(Listing $listing, DateTimeImmutable $now = new DateTimeImmutable()): ShoppingCartItem
    {
        $listing->assertPurchasableBy($this->buyer, $now);
        if ($this->items->count() >= self::MAXIMUM_ITEMS) {
            throw new InvalidCart('Een winkelmand kan maximaal 100 kaarten bevatten.');
        }
        foreach ($this->items as $item) {
            if ($item->getListing()->getId()->equals($listing->getId())) {
                throw new InvalidCart('Deze listing staat al in je winkelmand.');
            }
        }

        $item = ShoppingCartItem::forListing($this, $listing, $now);
        $this->items->add($item);
        $this->updatedAt = $now;

        return $item;
    }

    public function remove(string $itemId): void
    {
        foreach ($this->items as $item) {
            if ($item->getId()->toRfc4122() !== $itemId) {
                continue;
            }

            $this->items->removeElement($item);
            $this->updatedAt = new DateTimeImmutable();

            return;
        }

        throw new CartItemNotFound('Winkelmanditem niet gevonden.');
    }

    public function clear(): void
    {
        $this->items->clear();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
