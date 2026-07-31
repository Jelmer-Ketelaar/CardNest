<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Exception\CannotPurchaseOwnListing;
use App\Marketplace\Domain\Exception\InvalidListing;
use App\Marketplace\Domain\Exception\InvalidListingTransition;
use App\Marketplace\Domain\Exception\ListingUnavailable;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'listings')]
#[ORM\Index(name: 'idx_listings_status_published', columns: ['status', 'published_at'])]
#[ORM\Index(name: 'idx_listings_seller_status', columns: ['seller_id', 'status'])]
#[ORM\Index(name: 'idx_listings_collection_item', columns: ['collection_item_id'])]
#[ORM\UniqueConstraint(
    name: 'uniq_collection_item_blocking_listing',
    columns: ['collection_item_id'],
    options: ['where' => "((status)::text = ANY ((ARRAY['pending_review'::character varying, 'active'::character varying, 'reserved'::character varying, 'sold'::character varying])::text[]))"],
)]
final class Listing
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'price_amount')]
    private int $priceAmount;

    #[ORM\Column(name: 'price_currency', length: 3)]
    private string $priceCurrency;

    /** @var Collection<int, ListingImage> */
    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: ListingImage::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['side' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ListingShippingMethod> */
    #[ORM\OneToMany(
        mappedBy: 'listing',
        targetEntity: ListingShippingMethod::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['code' => 'ASC'])]
    private Collection $shippingMethods;

    /** @var Collection<int, ListingPriceHistory> */
    #[ORM\OneToMany(
        mappedBy: 'listing',
        targetEntity: ListingPriceHistory::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['changedAt' => 'DESC'])]
    private Collection $priceHistory;

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'cancelled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<ShippingOption>        $shippingOptions
     * @param list<ListingPhotoReference> $photos
     *
     * ORM-mapped constructor fields are intentionally not readonly: checkout
     * refreshes this aggregate after acquiring its pessimistic row lock
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'seller_id', nullable: false, onDelete: 'RESTRICT')]
        private User $seller,
        #[ORM\ManyToOne(targetEntity: CollectionItem::class)]
        #[ORM\JoinColumn(name: 'collection_item_id', nullable: false, onDelete: 'RESTRICT')]
        private CollectionItem $collectionItem,
        Money $price,
        array $shippingOptions,
        array $photos,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description = null,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?DateTimeImmutable $expiresAt = null,
        #[ORM\Column(length: 30, enumType: ListingStatus::class)]
        private ListingStatus $status = ListingStatus::Draft,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (!$seller->getId()->equals($collectionItem->getOwner()->getId())) {
            throw new InvalidListing('Alleen de eigenaar kan een collectiekaart aanbieden.');
        }
        if ($collectionItem->getStatus() !== CollectionItemStatus::InCollection) {
            throw new InvalidListing('Alleen een beschikbare collectiekaart kan worden aangeboden.');
        }

        $this->id = Uuid::v7();
        $this->images = new ArrayCollection();
        $this->shippingMethods = new ArrayCollection();
        $this->priceHistory = new ArrayCollection();
        $this->description = self::normalizeDescription($description);
        $this->assertExpiration($expiresAt, $createdAt);
        $this->updatedAt = $createdAt;
        $this->setInitialPrice($price);
        $this->replaceShippingOptions($shippingOptions);
        $this->replacePhotos($photos);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    public function getCollectionItem(): CollectionItem
    {
        return $this->collectionItem;
    }

    public function getPrice(): Money
    {
        return new Money($this->priceAmount, $this->priceCurrency);
    }

    /** @return Collection<int, ListingImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /** @return Collection<int, ListingShippingMethod> */
    public function getShippingMethods(): Collection
    {
        return $this->shippingMethods;
    }

    /** @return Collection<int, ListingPriceHistory> */
    public function getPriceHistory(): Collection
    {
        return $this->priceHistory;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getStatus(): ListingStatus
    {
        return $this->status;
    }

    public function getWorkflowStatus(): string
    {
        return $this->status->value;
    }

    public function setWorkflowStatus(string $status): void
    {
        $nextStatus = ListingStatus::from($status);
        $now = new DateTimeImmutable();
        if ($nextStatus === ListingStatus::Active && $this->publishedAt === null) {
            $this->publishedAt = $now;
        }
        if ($nextStatus === ListingStatus::Cancelled) {
            $this->cancelledAt = $now;
        }

        $this->status = $nextStatus;
        $this->updatedAt = $now;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changePrice(Money $price): void
    {
        $this->assertEditable();
        $this->assertPrice($price);
        if ($price->amount === $this->priceAmount && $price->currency === $this->priceCurrency) {
            return;
        }

        $previous = $this->priceAmount;
        $this->priceAmount = $price->amount;
        $this->priceCurrency = $price->currency;
        $this->priceHistory->add(new ListingPriceHistory($this, $previous, $price->amount, $price->currency));
        $this->touch();
    }

    public function changeDescription(?string $description): void
    {
        $this->assertEditable();
        $this->description = self::normalizeDescription($description);
        $this->touch();
    }

    public function changeExpiration(?DateTimeImmutable $expiresAt): void
    {
        $this->assertEditable();
        $this->assertExpiration($expiresAt, new DateTimeImmutable());
        $this->expiresAt = $expiresAt;
        $this->touch();
    }

    /** @param list<ListingPhotoReference> $photos */
    public function replacePhotos(array $photos): void
    {
        $this->assertEditable();

        $bySide = [];
        foreach ($photos as $photo) {
            $side = $photo->side->value;
            if (isset($bySide[$side])) {
                throw new InvalidListing('Een listing kan per zijde maar één hoofdfoto hebben.');
            }
            $bySide[$side] = $photo;
        }
        if (!isset($bySide[ListingImageSide::Front->value], $bySide[ListingImageSide::Back->value])) {
            throw new InvalidListing('Een listing vereist een voor- en achterkantfoto.');
        }

        $existingBySide = [];
        foreach ($this->images as $image) {
            $existingBySide[$image->getSide()->value] = $image;
        }
        foreach ($bySide as $photo) {
            $existing = $existingBySide[$photo->side->value] ?? null;
            if ($existing instanceof ListingImage) {
                $existing->replaceReference($photo);
            } else {
                $this->images->add(ListingImage::fromReference($this, $photo));
            }
        }
        $this->touch();
    }

    public function assertPublishable(): void
    {
        if ($this->status !== ListingStatus::Draft) {
            throw new InvalidListingTransition('Alleen een conceptlisting kan worden gepubliceerd.');
        }
        if ($this->collectionItem->getStatus() !== CollectionItemStatus::InCollection) {
            throw new InvalidListing('De fysieke kaart is niet meer beschikbaar voor verkoop.');
        }
        if ($this->expiresAt !== null && $this->expiresAt <= new DateTimeImmutable()) {
            throw new InvalidListing('Een verlopen listing kan niet worden gepubliceerd.');
        }
    }

    public function assertCancellable(): void
    {
        if (!in_array($this->status, [ListingStatus::Draft, ListingStatus::Active], true)) {
            throw new InvalidListingTransition('Deze listing kan niet worden geannuleerd.');
        }
    }

    public function assertPurchasableBy(User $buyer, DateTimeImmutable $now = new DateTimeImmutable()): void
    {
        if ($buyer->getId()->equals($this->seller->getId())) {
            throw new CannotPurchaseOwnListing('Je kunt je eigen listing niet kopen.');
        }
        if (!$this->isAvailableAt($now)) {
            throw new ListingUnavailable('Deze listing is niet meer beschikbaar.');
        }
    }

    public function isAvailableAt(DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        return $this->status === ListingStatus::Active
            && ($this->expiresAt === null || $this->expiresAt > $now);
    }

    public function shippingPriceFor(ShippingMethodCode $code): ?Money
    {
        foreach ($this->shippingMethods as $shippingMethod) {
            if ($shippingMethod->getCode() === $code) {
                return $shippingMethod->getPrice();
            }
        }

        return null;
    }

    private function setInitialPrice(Money $price): void
    {
        $this->assertPrice($price);
        $this->priceAmount = $price->amount;
        $this->priceCurrency = $price->currency;
        $this->priceHistory->add(new ListingPriceHistory($this, null, $price->amount, $price->currency, $this->createdAt));
    }

    /** @param list<ShippingOption> $shippingOptions */
    private function replaceShippingOptions(array $shippingOptions): void
    {
        if ($shippingOptions === []) {
            throw new InvalidListing('Kies minimaal één verzend- of afhaaloptie.');
        }

        $byCode = [];
        foreach ($shippingOptions as $option) {
            $code = $option->code->value;
            if (isset($byCode[$code])) {
                throw new InvalidListing('Een verzendoptie kan maar één keer worden gekozen.');
            }
            $byCode[$code] = $option;
        }

        foreach ($byCode as $option) {
            $this->shippingMethods->add(ListingShippingMethod::fromOption($this, $option));
        }
    }

    private function assertEditable(): void
    {
        if (!in_array($this->status, [ListingStatus::Draft, ListingStatus::Active], true)) {
            throw new InvalidListingTransition('Een gereserveerde, verkochte of gesloten listing kan niet worden gewijzigd.');
        }
    }

    private function assertPrice(Money $price): void
    {
        if ($price->amount <= 0) {
            throw new InvalidListing('De listingprijs moet groter zijn dan nul.');
        }
        if ($price->currency !== 'EUR') {
            throw new InvalidListing('Listings ondersteunen momenteel alleen EUR.');
        }
    }

    private function assertExpiration(?DateTimeImmutable $expiresAt, DateTimeImmutable $minimum): void
    {
        if ($expiresAt !== null && $expiresAt <= $minimum) {
            throw new InvalidListing('De einddatum moet in de toekomst liggen.');
        }
    }

    private static function normalizeDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }
        $description = trim($description);
        if (mb_strlen($description) > 5000) {
            throw new InvalidListing('De listingomschrijving mag maximaal 5000 tekens bevatten.');
        }

        return $description;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
