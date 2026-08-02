<?php

declare(strict_types=1);

namespace App\Binder\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogCard;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'binder_slots')]
#[ORM\UniqueConstraint(name: 'uniq_binder_slot_position', columns: ['binder_page_id', 'position'])]
#[ORM\Index(name: 'idx_binder_slots_reserved_card', columns: ['reserved_catalog_card_id'])]
final class BinderSlot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'placeholder_label', length: 180, nullable: true)]
    private ?string $placeholderLabel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: BinderPage::class, inversedBy: 'slots')]
        #[ORM\JoinColumn(name: 'binder_page_id', nullable: false, onDelete: 'CASCADE')]
        private readonly BinderPage $page,
        #[ORM\Column]
        private readonly int $position,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        #[ORM\ManyToOne(targetEntity: CatalogCard::class)]
        #[ORM\JoinColumn(name: 'reserved_catalog_card_id', nullable: true, onDelete: 'RESTRICT')]
        private ?CatalogCard $reservedCatalogCard = null,
    ) {
        $this->id = Uuid::v7();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPage(): BinderPage
    {
        return $this->page;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getReservedCatalogCard(): ?CatalogCard
    {
        return $this->reservedCatalogCard;
    }

    public function getPlaceholderLabel(): ?string
    {
        return $this->placeholderLabel;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function reserveCatalogCard(CatalogCard $card): void
    {
        $this->reservedCatalogCard = $card;
        $this->placeholderLabel = null;
        $this->touch();
    }

    public function setPlaceholderLabel(string $label): void
    {
        $label = trim($label);
        $this->placeholderLabel = $label === '' ? null : $label;
        $this->reservedCatalogCard = null;
        $this->touch();
    }

    public function clearPlaceholder(): void
    {
        if ($this->reservedCatalogCard === null && $this->placeholderLabel === null) {
            return;
        }

        $this->reservedCatalogCard = null;
        $this->placeholderLabel = null;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
