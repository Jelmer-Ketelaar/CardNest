<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Records that a given CatalogCard was printed in a specific variant (holo,
 * reverse holo, ...). Instances are created through CatalogCard::addVariant()
 * so the "no duplicate variant per card" rule lives in a single place.
 */
#[ORM\Entity]
#[ORM\Table(name: 'card_variants')]
#[ORM\UniqueConstraint(name: 'uniq_card_variants_card_code', columns: ['catalog_card_id', 'code'])]
final class CardVariant
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogCard::class, inversedBy: 'variants')]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CatalogCard $catalogCard,
        #[ORM\Column(length: 40, enumType: VariantCode::class)]
        private readonly VariantCode $code,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCatalogCard(): CatalogCard
    {
        return $this->catalogCard;
    }

    public function getCode(): VariantCode
    {
        return $this->code;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
