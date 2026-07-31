<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\CardNumberSortKey;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Describes an official card variant printed by the manufacturer, e.g. "Mudkip,
 * McDonald's Collection 2021, number 19/25". This is a catalog record only: it
 * is never owned by a user. A physical card a user owns is a CollectionItem
 * (Collection module) referencing this record.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_cards')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_cards_set_number', columns: ['set_id', 'number'])]
#[ORM\Index(name: 'idx_catalog_cards_name', columns: ['name'])]
#[ORM\Index(name: 'idx_catalog_cards_number_sort_key', columns: ['number_sort_key'])]
#[ORM\Index(name: 'idx_catalog_cards_rarity', columns: ['rarity'])]
final class CatalogCard
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'number_sort_key', length: 160)]
    private readonly string $numberSortKey;

    /** @var Collection<int, CardVariant> */
    #[ORM\OneToMany(
        mappedBy: 'catalogCard',
        targetEntity: CardVariant::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $variants;

    /** @var Collection<int, CardExternalReference> */
    #[ORM\OneToMany(
        mappedBy: 'catalogCard',
        targetEntity: CardExternalReference::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $externalReferences;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: CardSet::class)]
        #[ORM\JoinColumn(name: 'set_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly CardSet $set,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(length: 50)]
        private readonly string $number,
        #[ORM\Column(length: 100)]
        private string $rarity,
        #[ORM\Column(name: 'card_type', length: 100)]
        private string $cardType,
        #[ORM\Column(name: 'total_number_in_set', length: 50, nullable: true)]
        private readonly ?string $totalNumberInSet = null,
        #[ORM\Column(name: 'primary_collectible', length: 180, nullable: true)]
        private ?string $primaryCollectible = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $illustrator = null,
        #[ORM\Column(name: 'image_url', length: 1024, nullable: true)]
        private ?string $imageUrl = null,
        #[ORM\Column(name: 'thumbnail_url', length: 1024, nullable: true)]
        private ?string $thumbnailUrl = null,
        #[ORM\Column(name: 'release_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private readonly ?DateTimeImmutable $releaseDate = null,
        #[ORM\Column(type: Types::JSONB)]
        private array $metadata = [],
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->numberSortKey = CardNumberSortKey::generate($number);
        $this->variants = new ArrayCollection();
        $this->externalReferences = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSet(): CardSet
    {
        return $this->set;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getNumberSortKey(): string
    {
        return $this->numberSortKey;
    }

    public function getTotalNumberInSet(): ?string
    {
        return $this->totalNumberInSet;
    }

    public function getRarity(): string
    {
        return $this->rarity;
    }

    public function getCardType(): string
    {
        return $this->cardType;
    }

    public function getPrimaryCollectible(): ?string
    {
        return $this->primaryCollectible;
    }

    public function getIllustrator(): ?string
    {
        return $this->illustrator;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function getReleaseDate(): ?DateTimeImmutable
    {
        return $this->releaseDate;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Registers that this card was printed in the given variant. This is the
     * only supported way to create a CardVariant: it keeps the "a card cannot
     * have the same variant twice" rule in one place.
     */
    public function addVariant(VariantCode $code): CardVariant
    {
        foreach ($this->variants as $existing) {
            if ($existing->getCode() === $code) {
                throw new LogicException(sprintf('Variant "%s" is already registered for this card.', $code->value));
            }
        }

        $variant = new CardVariant($this, $code);
        $this->variants->add($variant);

        return $variant;
    }

    /** @return Collection<int, CardVariant> */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    /** @param array<string, mixed> $rawData */
    public function addExternalReference(
        string $sourceName,
        string $externalId,
        array $rawData = [],
    ): CardExternalReference {
        foreach ($this->externalReferences as $existing) {
            if ($existing->getSourceName() === $sourceName && $existing->getExternalId() === $externalId) {
                $message = sprintf(
                    'External reference "%s:%s" is already registered for this card.',
                    $sourceName,
                    $externalId,
                );

                throw new LogicException($message);
            }
        }

        $reference = new CardExternalReference($this, $sourceName, $externalId, $rawData);
        $this->externalReferences->add($reference);

        return $reference;
    }

    /** @return Collection<int, CardExternalReference> */
    public function getExternalReferences(): Collection
    {
        return $this->externalReferences;
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
