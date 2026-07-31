<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Order\Domain\Exception\InvalidCollectionImport;
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

    #[ORM\ManyToOne(targetEntity: CollectionItem::class)]
    #[ORM\JoinColumn(name: 'imported_collection_item_id', nullable: true, onDelete: 'RESTRICT')]
    private ?CollectionItem $importedCollectionItem = null;

    #[ORM\Column(name: 'imported_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $importedAt = null;

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

    public function canBeImportedBy(User $buyer): bool
    {
        $source = $this->listing->getCollectionItem();

        return $this->importedCollectionItem === null
            && $this->order->getStatus() === OrderStatus::Completed
            && $this->order->getBuyer()->getId()->equals($buyer->getId())
            && $this->order->getSeller()->getId()->equals($source->getOwner()->getId())
            && $source->getStatus() === CollectionItemStatus::Archived;
    }

    public function recordCollectionImport(
        CollectionItem $collectionItem,
        User $buyer,
        DateTimeImmutable $importedAt = new DateTimeImmutable(),
    ): void {
        $source = $this->listing->getCollectionItem();
        if (!$this->canBeImportedBy($buyer)) {
            throw new InvalidCollectionImport('Alleen een nog niet geïmporteerd item uit een voltooide aankoop kan worden toegevoegd.');
        }
        if (!$collectionItem->getOwner()->getId()->equals($buyer->getId())) {
            throw new InvalidCollectionImport('De nieuwe collectiekaart moet eigendom zijn van de koper.');
        }
        if ($collectionItem->getStatus() !== CollectionItemStatus::InCollection) {
            throw new InvalidCollectionImport('De nieuwe collectiekaart moet actief in de collectie staan.');
        }
        if (!$collectionItem->getCatalogCard()->getId()->equals($source->getCatalogCard()->getId())
            || !$collectionItem->getVariant()->getId()->equals($source->getVariant()->getId())) {
            throw new InvalidCollectionImport('De nieuwe collectiekaart moet dezelfde kaart en variant behouden.');
        }
        $purchasePrice = $collectionItem->getPurchasePrice();
        $paidAt = $this->order->getPaidAt();
        if ($purchasePrice === null
            || $purchasePrice->amount !== $this->priceAmount
            || $purchasePrice->currency !== $this->priceCurrency
            || $paidAt === null
            || $collectionItem->getPurchasedAt()?->format('Y-m-d') !== $paidAt->format('Y-m-d')) {
            throw new InvalidCollectionImport('Aankoopprijs, valuta en aankoopdatum moeten uit de order worden overgenomen.');
        }
        $completedAt = $this->order->getCompletedAt();
        if ($completedAt === null || $importedAt < $completedAt) {
            throw new InvalidCollectionImport('De collectie-import kan niet vóór de ordervoltooiing plaatsvinden.');
        }

        $this->importedCollectionItem = $collectionItem;
        $this->importedAt = $importedAt;
    }

    public function getImportedCollectionItem(): ?CollectionItem
    {
        return $this->importedCollectionItem;
    }

    public function getImportedAt(): ?DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
