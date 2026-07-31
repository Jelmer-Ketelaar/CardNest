<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Entity;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Wishlist\Domain\Exception\InvalidWishlistItem;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'wishlist_items')]
#[ORM\Index(name: 'idx_wishlist_items_wishlist', columns: ['wishlist_id'])]
#[ORM\Index(name: 'idx_wishlist_items_open_card', columns: ['catalog_card_id', 'completed_at'])]
final class WishlistItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'max_price', nullable: true)]
    private ?int $maxPriceAmount;

    #[ORM\Column(name: 'max_currency', length: 3, nullable: true)]
    private ?string $maxPriceCurrency;

    #[ORM\Column(name: 'fulfilled_quantity')]
    private int $fulfilledQuantity = 0;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Wishlist::class, inversedBy: 'items')]
        #[ORM\JoinColumn(name: 'wishlist_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Wishlist $wishlist,
        #[ORM\ManyToOne(targetEntity: CatalogCard::class)]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly CatalogCard $catalogCard,
        #[ORM\Column(name: 'desired_language', length: 5, nullable: true, enumType: CardLanguage::class)]
        private readonly ?CardLanguage $desiredLanguage,
        #[ORM\ManyToOne(targetEntity: CardVariant::class)]
        #[ORM\JoinColumn(name: 'variant_id', nullable: true, onDelete: 'RESTRICT')]
        private readonly ?CardVariant $desiredVariant,
        #[ORM\Column(name: 'minimum_condition', length: 20, nullable: true, enumType: CardCondition::class)]
        private readonly ?CardCondition $minimumCondition,
        ?Money $maxPrice,
        #[ORM\Column(name: 'desired_quantity')]
        private readonly int $desiredQuantity,
        #[ORM\Column(length: 20, enumType: WishlistPriority::class)]
        private readonly WishlistPriority $priority = WishlistPriority::Normal,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $notes = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($desiredQuantity < 1 || $desiredQuantity > 100) {
            throw new InvalidWishlistItem('De gewenste hoeveelheid moet tussen 1 en 100 liggen.');
        }
        if ($desiredVariant instanceof CardVariant
            && !$desiredVariant->getCatalogCard()->getId()->equals($catalogCard->getId())) {
            throw new InvalidWishlistItem('De gewenste variant hoort niet bij de gekozen cataloguskaart.');
        }
        if ($maxPrice instanceof Money && $maxPrice->currency !== 'EUR') {
            throw new InvalidWishlistItem('Wishlists ondersteunen momenteel alleen EUR.');
        }

        $this->id = Uuid::v7();
        $this->maxPriceAmount = $maxPrice?->amount;
        $this->maxPriceCurrency = $maxPrice?->currency;
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getWishlist(): Wishlist
    {
        return $this->wishlist;
    }

    public function getCatalogCard(): CatalogCard
    {
        return $this->catalogCard;
    }

    public function getDesiredLanguage(): ?CardLanguage
    {
        return $this->desiredLanguage;
    }

    public function getDesiredVariant(): ?CardVariant
    {
        return $this->desiredVariant;
    }

    public function getMinimumCondition(): ?CardCondition
    {
        return $this->minimumCondition;
    }

    public function getMaxPrice(): ?Money
    {
        if ($this->maxPriceAmount === null || $this->maxPriceCurrency === null) {
            return null;
        }

        return new Money($this->maxPriceAmount, $this->maxPriceCurrency);
    }

    public function getDesiredQuantity(): int
    {
        return $this->desiredQuantity;
    }

    public function getFulfilledQuantity(): int
    {
        return $this->fulfilledQuantity;
    }

    public function getRemainingQuantity(): int
    {
        return $this->desiredQuantity - $this->fulfilledQuantity;
    }

    public function getPriority(): WishlistPriority
    {
        return $this->priority;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isCompleted(): bool
    {
        return $this->fulfilledQuantity >= $this->desiredQuantity;
    }

    public function matches(CollectionItem $item): bool
    {
        if ($this->isCompleted() || $item->getStatus() !== CollectionItemStatus::InCollection) {
            return false;
        }
        if (!$item->getOwner()->getId()->equals($this->wishlist->getOwner()->getId())) {
            return false;
        }
        if (!$item->getCatalogCard()->getId()->equals($this->catalogCard->getId())) {
            return false;
        }
        if ($this->desiredLanguage instanceof CardLanguage && $item->getLanguage() !== $this->desiredLanguage) {
            return false;
        }
        if ($this->desiredVariant instanceof CardVariant
            && !$item->getVariant()->getId()->equals($this->desiredVariant->getId())) {
            return false;
        }
        if ($this->minimumCondition instanceof CardCondition
            && !$item->getCondition()->meetsMinimum($this->minimumCondition)) {
            return false;
        }

        $maxPrice = $this->getMaxPrice();
        if (!$maxPrice instanceof Money) {
            return true;
        }

        $purchasePrice = $item->getPurchasePrice();

        return $purchasePrice instanceof Money
            && $purchasePrice->currency === $maxPrice->currency
            && $purchasePrice->amount <= $maxPrice->amount;
    }

    public function fulfill(int $quantity = 1): void
    {
        if ($quantity < 1) {
            throw new InvalidWishlistItem('De vervulde hoeveelheid moet minimaal één zijn.');
        }
        if ($this->isCompleted()) {
            return;
        }

        $this->fulfilledQuantity = min($this->desiredQuantity, $this->fulfilledQuantity + $quantity);
        $this->updatedAt = new DateTimeImmutable();
        if ($this->isCompleted()) {
            $this->completedAt = $this->updatedAt;
        }
        $this->wishlist->touch();
    }
}
