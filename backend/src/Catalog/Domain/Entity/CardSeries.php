<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Groups related card sets, e.g. "Scarlet & Violet" groups every set released
 * under that generation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'card_series')]
#[ORM\UniqueConstraint(name: 'uniq_card_series_name', columns: ['name'])]
final class CardSeries
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(length: 180)]
        private readonly string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
