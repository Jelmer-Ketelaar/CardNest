<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Catalog\Domain\Entity\CardExternalReference;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Provider\MarketPriceProvider;
use App\Pricing\Domain\Provider\MarketPriceQuote;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;

final readonly class RefreshCardPrices
{
    public const string SOURCE = 'tcgdex';
    public const string MARKET = 'cardmarket';
    public const string CURRENCY = 'EUR';
    private const string OBSERVATION_TIMEZONE = 'Europe/Amsterdam';

    public function __construct(
        private CatalogCardRepository $catalogCards,
        private PriceSnapshotRepository $snapshots,
        private MarketPriceProvider $provider,
        private LockFactory $lockFactory,
    ) {
    }

    public function refresh(
        string $cardId,
        bool $force = false,
        ?DateTimeImmutable $now = null,
    ): PriceRefreshStatus {
        $card = $this->catalogCards->get($cardId);
        if (!$card instanceof CatalogCard) {
            return PriceRefreshStatus::NotFound;
        }

        $observedAt = $now ?? new DateTimeImmutable();
        $observedOn = $this->observedOn($observedAt);
        $lock = $this->lockFactory->createLock(sprintf(
            'pricing-refresh-%s-%s',
            $card->getId()->toRfc4122(),
            $observedOn->format('Y-m-d'),
        ), 120.0);
        $lock->acquire(true);

        try {
            return $this->refreshLocked($card, $force, $observedAt, $observedOn);
        } finally {
            $lock->release();
        }
    }

    private function refreshLocked(
        CatalogCard $card,
        bool $force,
        DateTimeImmutable $observedAt,
        DateTimeImmutable $observedOn,
    ): PriceRefreshStatus {
        if (!$force && $this->snapshots->hasObservationOn($card, self::SOURCE, self::MARKET, $observedOn)) {
            return PriceRefreshStatus::Current;
        }

        $reference = $this->tcgDexReference($card);
        if (!$reference instanceof CardExternalReference) {
            return PriceRefreshStatus::Unsupported;
        }

        [$language, $externalCardId] = explode(':', $reference->getExternalId(), 2);
        $result = $this->provider->fetch($language, $externalCardId);
        $dailySnapshots = array_map(
            fn (MarketPriceQuote $quote): PriceSnapshot => new PriceSnapshot(
                catalogCard: $card,
                source: $result->source,
                market: $result->market,
                variant: $quote->variant,
                currency: $result->currency,
                lowest: $quote->lowest,
                average: $quote->average,
                trend: $quote->trend,
                averageOneDay: $quote->averageOneDay,
                averageSevenDays: $quote->averageSevenDays,
                averageThirtyDays: $quote->averageThirtyDays,
                sourceUpdatedAt: $result->sourceUpdatedAt,
                observedAt: $observedAt,
                observedOn: $observedOn,
            ),
            $result->quotes,
        );

        if ($dailySnapshots === []) {
            $dailySnapshots[] = PriceSnapshot::unavailable(
                $card,
                $result->source,
                $result->market,
                $result->currency,
                $observedAt,
                $observedOn,
            );
        }

        $this->snapshots->replaceDaily(
            $card,
            $result->source,
            $result->market,
            $observedOn,
            $dailySnapshots,
        );

        return $result->quotes === [] ? PriceRefreshStatus::NoData : PriceRefreshStatus::Refreshed;
    }

    private function tcgDexReference(CatalogCard $card): ?CardExternalReference
    {
        foreach ($card->getExternalReferences() as $reference) {
            if ($reference->getSourceName() !== self::SOURCE) {
                continue;
            }

            $parts = explode(':', $reference->getExternalId(), 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                return $reference;
            }
        }

        return null;
    }

    private function observedOn(DateTimeImmutable $observedAt): DateTimeImmutable
    {
        $timezone = new DateTimeZone(self::OBSERVATION_TIMEZONE);
        $date = $observedAt->setTimezone($timezone)->format('Y-m-d');

        return new DateTimeImmutable($date, $timezone);
    }
}
