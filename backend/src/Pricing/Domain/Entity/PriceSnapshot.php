<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'price_snapshots')]
#[ORM\UniqueConstraint(
    name: 'uniq_price_snapshot_card_source_variant_day',
    columns: ['catalog_card_id', 'source', 'market', 'variant', 'observed_on'],
)]
#[ORM\Index(name: 'idx_price_snapshots_card_observed', columns: ['catalog_card_id', 'observed_on'])]
final class PriceSnapshot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'lowest_amount', nullable: true)]
    private ?int $lowestAmount;

    #[ORM\Column(name: 'average_amount', nullable: true)]
    private ?int $averageAmount;

    #[ORM\Column(name: 'trend_amount', nullable: true)]
    private ?int $trendAmount;

    #[ORM\Column(name: 'average_one_day_amount', nullable: true)]
    private ?int $averageOneDayAmount;

    #[ORM\Column(name: 'average_seven_days_amount', nullable: true)]
    private ?int $averageSevenDaysAmount;

    #[ORM\Column(name: 'average_thirty_days_amount', nullable: true)]
    private ?int $averageThirtyDaysAmount;

    #[ORM\Column(name: 'source_updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $sourceUpdatedAt;

    #[ORM\Column(name: 'observed_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $observedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogCard::class)]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CatalogCard $catalogCard,
        #[ORM\Column(length: 50)]
        private readonly string $source,
        #[ORM\Column(length: 50)]
        private readonly string $market,
        #[ORM\Column(length: 30, enumType: PriceVariant::class)]
        private readonly PriceVariant $variant,
        #[ORM\Column(length: 3)]
        private readonly string $currency,
        ?Money $lowest,
        ?Money $average,
        ?Money $trend,
        ?Money $averageOneDay,
        ?Money $averageSevenDays,
        ?Money $averageThirtyDays,
        ?DateTimeImmutable $sourceUpdatedAt,
        DateTimeImmutable $observedAt,
        #[ORM\Column(name: 'observed_on', type: Types::DATE_IMMUTABLE)]
        private readonly DateTimeImmutable $observedOn,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->replaceMeasurements(
            $lowest,
            $average,
            $trend,
            $averageOneDay,
            $averageSevenDays,
            $averageThirtyDays,
            $sourceUpdatedAt,
            $observedAt,
        );
        $this->updatedAt = $createdAt;
    }

    public static function unavailable(
        CatalogCard $card,
        string $source,
        string $market,
        string $currency,
        DateTimeImmutable $observedAt,
        DateTimeImmutable $observedOn,
    ): self {
        return new self(
            catalogCard: $card,
            source: $source,
            market: $market,
            variant: PriceVariant::Unavailable,
            currency: $currency,
            lowest: null,
            average: null,
            trend: null,
            averageOneDay: null,
            averageSevenDays: null,
            averageThirtyDays: null,
            sourceUpdatedAt: null,
            observedAt: $observedAt,
            observedOn: $observedOn,
        );
    }

    public function synchronizeFrom(self $snapshot): void
    {
        if ($snapshot->source !== $this->source
            || $snapshot->market !== $this->market
            || $snapshot->variant !== $this->variant
            || $snapshot->currency !== $this->currency
            || !$snapshot->catalogCard->getId()->equals($this->catalogCard->getId())
            || $snapshot->observedOn->format('Y-m-d') !== $this->observedOn->format('Y-m-d')) {
            throw new InvalidArgumentException('Only the same daily price snapshot can be synchronized.');
        }

        $this->replaceMeasurements(
            $snapshot->getLowest(),
            $snapshot->getAverage(),
            $snapshot->getTrend(),
            $snapshot->getAverageOneDay(),
            $snapshot->getAverageSevenDays(),
            $snapshot->getAverageThirtyDays(),
            $snapshot->sourceUpdatedAt,
            $snapshot->observedAt,
        );
        $this->updatedAt = $snapshot->observedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCatalogCard(): CatalogCard
    {
        return $this->catalogCard;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getMarket(): string
    {
        return $this->market;
    }

    public function getVariant(): PriceVariant
    {
        return $this->variant;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLowest(): ?Money
    {
        return $this->money($this->lowestAmount);
    }

    public function getAverage(): ?Money
    {
        return $this->money($this->averageAmount);
    }

    public function getTrend(): ?Money
    {
        return $this->money($this->trendAmount);
    }

    public function getAverageOneDay(): ?Money
    {
        return $this->money($this->averageOneDayAmount);
    }

    public function getAverageSevenDays(): ?Money
    {
        return $this->money($this->averageSevenDaysAmount);
    }

    public function getAverageThirtyDays(): ?Money
    {
        return $this->money($this->averageThirtyDaysAmount);
    }

    public function getSourceUpdatedAt(): ?DateTimeImmutable
    {
        return $this->sourceUpdatedAt;
    }

    public function getObservedAt(): DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function getObservedOn(): DateTimeImmutable
    {
        return $this->observedOn;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function replaceMeasurements(
        ?Money $lowest,
        ?Money $average,
        ?Money $trend,
        ?Money $averageOneDay,
        ?Money $averageSevenDays,
        ?Money $averageThirtyDays,
        ?DateTimeImmutable $sourceUpdatedAt,
        DateTimeImmutable $observedAt,
    ): void {
        $prices = [$lowest, $average, $trend, $averageOneDay, $averageSevenDays, $averageThirtyDays];
        foreach ($prices as $price) {
            if ($price instanceof Money && $price->currency !== $this->currency) {
                throw new InvalidArgumentException('All snapshot prices must use the snapshot currency.');
            }
        }

        $hasPrice = array_any($prices, static fn (?Money $price): bool => $price instanceof Money);
        if ($this->variant === PriceVariant::Unavailable && $hasPrice) {
            throw new InvalidArgumentException('An unavailable snapshot cannot contain prices.');
        }
        if ($this->variant !== PriceVariant::Unavailable && !$hasPrice) {
            throw new InvalidArgumentException('An available snapshot must contain at least one price.');
        }

        $this->lowestAmount = $lowest?->amount;
        $this->averageAmount = $average?->amount;
        $this->trendAmount = $trend?->amount;
        $this->averageOneDayAmount = $averageOneDay?->amount;
        $this->averageSevenDaysAmount = $averageSevenDays?->amount;
        $this->averageThirtyDaysAmount = $averageThirtyDays?->amount;
        $this->sourceUpdatedAt = $sourceUpdatedAt;
        $this->observedAt = $observedAt;
    }

    private function money(?int $amount): ?Money
    {
        return $amount === null ? null : new Money($amount, $this->currency);
    }
}
