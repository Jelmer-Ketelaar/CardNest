<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Domain\Exception\InvalidWishlist;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'wishlists')]
#[ORM\Index(name: 'idx_wishlists_owner', columns: ['owner_id'])]
final class Wishlist
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, WishlistItem> */
    #[ORM\OneToMany(mappedBy: 'wishlist', targetEntity: WishlistItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $items;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $owner,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->items = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, WishlistItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
        $this->touch();
    }

    public function addItem(WishlistItem $item): void
    {
        if ($item->getWishlist() !== $this) {
            throw new InvalidWishlist('Een wishlist-item hoort bij een andere wishlist.');
        }

        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $this->touch();
        }
    }

    public function removeItem(WishlistItem $item): void
    {
        if ($this->items->removeElement($item)) {
            $this->touch();
        }
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidWishlist('Een wishlist moet een naam hebben.');
        }

        return $name;
    }
}
