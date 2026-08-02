<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_card_artists')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_card_artist', columns: ['catalog_card_id', 'artist_id'])]
#[ORM\Index(name: 'idx_catalog_card_artists_artist', columns: ['artist_id'])]
final class CatalogCardArtist
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogCard::class, inversedBy: 'artistLinks')]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CatalogCard $catalogCard,
        #[ORM\ManyToOne(targetEntity: Artist::class)]
        #[ORM\JoinColumn(name: 'artist_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Artist $artist,
        #[ORM\Column(length: 40)]
        private readonly string $role = 'primary_illustrator',
        #[ORM\Column(name: 'display_order')]
        private readonly int $displayOrder = 0,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) { $this->id = Uuid::v7(); }

    public function getArtist(): Artist { return $this->artist; }
    public function getRole(): string { return $this->role; }
    public function getDisplayOrder(): int { return $this->displayOrder; }
}
