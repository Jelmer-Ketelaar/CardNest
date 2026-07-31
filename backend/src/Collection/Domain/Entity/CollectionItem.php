<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Collection\Domain\Exception\CardVariantDoesNotBelongToCard;
use App\Collection\Domain\Exception\ListedCollectionItemCannotBeChanged;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'collection_items')]
#[ORM\Index(name: 'idx_collection_items_owner_status', columns: ['owner_id', 'status'])]
#[ORM\Index(name: 'idx_collection_items_card', columns: ['catalog_card_id'])]
#[ORM\Index(name: 'idx_collection_items_variant', columns: ['variant_id'])]
#[ORM\Index(
    name: 'idx_collection_items_duplicates',
    columns: ['owner_id', 'catalog_card_id', 'language', 'variant_id', 'status'],
)]
final class CollectionItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'purchase_price', nullable: true)]
    private ?int $purchaseAmount;

    #[ORM\Column(name: 'purchase_currency', length: 3, nullable: true)]
    private ?string $purchaseCurrency;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $owner,
        #[ORM\ManyToOne(targetEntity: CatalogCard::class)]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly CatalogCard $catalogCard,
        #[ORM\ManyToOne(targetEntity: CardVariant::class)]
        #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'RESTRICT')]
        private CardVariant $variant,
        #[ORM\Column(length: 5, enumType: CardLanguage::class)]
        private CardLanguage $language,
        #[ORM\Column(length: 20, enumType: CardCondition::class)]
        private CardCondition $condition,
        ?Money $purchasePrice = null,
        #[ORM\Column(name: 'purchased_at', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?DateTimeImmutable $purchasedAt = null,
        #[ORM\Column(name: 'seller_name', length: 180, nullable: true)]
        private ?string $sellerName = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $notes = null,
        #[ORM\Column(length: 20, enumType: CollectionItemStatus::class)]
        private CollectionItemStatus $status = CollectionItemStatus::InCollection,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->assertVariantBelongsToCard($variant);
        $this->id = Uuid::v7();
        $this->purchaseAmount = $purchasePrice?->amount;
        $this->purchaseCurrency = $purchasePrice?->currency;
        $this->sellerName = self::normalizeOptionalText($sellerName);
        $this->notes = self::normalizeOptionalText($notes);
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

    public function getCatalogCard(): CatalogCard
    {
        return $this->catalogCard;
    }

    public function getVariant(): CardVariant
    {
        return $this->variant;
    }

    public function getLanguage(): CardLanguage
    {
        return $this->language;
    }

    public function getCondition(): CardCondition
    {
        return $this->condition;
    }

    public function getPurchasePrice(): ?Money
    {
        if ($this->purchaseAmount === null || $this->purchaseCurrency === null) {
            return null;
        }

        return new Money($this->purchaseAmount, $this->purchaseCurrency);
    }

    public function getPurchasedAt(): ?DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function getSellerName(): ?string
    {
        return $this->sellerName;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getStatus(): CollectionItemStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeLanguage(CardLanguage $language): void
    {
        $this->assertMutable();
        $this->language = $language;
        $this->touch();
    }

    public function changeVariant(CardVariant $variant): void
    {
        $this->assertMutable();
        $this->assertVariantBelongsToCard($variant);
        $this->variant = $variant;
        $this->touch();
    }

    public function changeCondition(CardCondition $condition): void
    {
        $this->assertMutable();
        $this->condition = $condition;
        $this->touch();
    }

    public function changePurchasePrice(?Money $purchasePrice): void
    {
        $this->assertMutable();
        $this->purchaseAmount = $purchasePrice?->amount;
        $this->purchaseCurrency = $purchasePrice?->currency;
        $this->touch();
    }

    public function changePurchasedAt(?DateTimeImmutable $purchasedAt): void
    {
        $this->assertMutable();
        $this->purchasedAt = $purchasedAt;
        $this->touch();
    }

    public function changeSellerName(?string $sellerName): void
    {
        $this->assertMutable();
        $this->sellerName = self::normalizeOptionalText($sellerName);
        $this->touch();
    }

    public function changeNotes(?string $notes): void
    {
        $this->assertMutable();
        $this->notes = self::normalizeOptionalText($notes);
        $this->touch();
    }

    public function archive(): void
    {
        if ($this->status === CollectionItemStatus::Archived) {
            return;
        }
        if ($this->status === CollectionItemStatus::ForSale) {
            throw new ListedCollectionItemCannotBeChanged('Een actief aangeboden kaart kan niet worden gearchiveerd.');
        }

        $this->status = CollectionItemStatus::Archived;
        $this->touch();
    }

    public function markForSale(): void
    {
        if ($this->status !== CollectionItemStatus::InCollection) {
            throw new ListedCollectionItemCannotBeChanged('Alleen een beschikbare collectiekaart kan worden aangeboden.');
        }

        $this->status = CollectionItemStatus::ForSale;
        $this->touch();
    }

    public function removeFromSale(): void
    {
        if ($this->status !== CollectionItemStatus::ForSale) {
            return;
        }

        $this->status = CollectionItemStatus::InCollection;
        $this->touch();
    }

    public function duplicateKey(): string
    {
        return self::buildDuplicateKey(
            $this->catalogCard->getId()->toRfc4122(),
            $this->language->value,
            $this->variant->getId()->toRfc4122(),
        );
    }

    public static function buildDuplicateKey(string $cardId, string $language, string $variantId): string
    {
        return $cardId.'|'.$language.'|'.$variantId;
    }

    private function assertMutable(): void
    {
        if ($this->status === CollectionItemStatus::Archived) {
            throw new ArchivedCollectionItemCannotBeChanged('Een gearchiveerde collectiekaart kan niet worden gewijzigd.');
        }
        if ($this->status === CollectionItemStatus::ForSale) {
            throw new ListedCollectionItemCannotBeChanged('Een actief aangeboden kaart kan niet worden gewijzigd.');
        }
    }

    private function assertVariantBelongsToCard(CardVariant $variant): void
    {
        if (!$variant->getCatalogCard()->getId()->equals($this->catalogCard->getId())) {
            throw new CardVariantDoesNotBelongToCard('De gekozen variant hoort niet bij deze cataloguskaart.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    private static function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
