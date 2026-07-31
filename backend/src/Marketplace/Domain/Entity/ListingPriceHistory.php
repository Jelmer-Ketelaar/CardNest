<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Entity;

use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'listing_price_history')]
#[ORM\Index(name: 'idx_listing_price_history_time', columns: ['listing_id', 'changed_at'])]
final class ListingPriceHistory
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Listing::class, inversedBy: 'priceHistory')]
        #[ORM\JoinColumn(name: 'listing_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Listing $listing,
        #[ORM\Column(name: 'previous_amount', nullable: true)]
        private readonly ?int $previousAmount,
        #[ORM\Column(name: 'new_amount')]
        private readonly int $newAmount,
        #[ORM\Column(length: 3)]
        private readonly string $currency,
        #[ORM\Column(name: 'changed_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $changedAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPreviousPrice(): ?Money
    {
        return $this->previousAmount !== null ? new Money($this->previousAmount, $this->currency) : null;
    }

    public function getNewPrice(): Money
    {
        return new Money($this->newAmount, $this->currency);
    }

    public function getChangedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }
}
