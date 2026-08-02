<?php

declare(strict_types=1);

namespace App\Pricing\Application\Dto;

use App\Pricing\Application\PriceRefreshStatus;
use App\Pricing\Application\RefreshCardPrices;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Shared\Domain\ValueObject\Money;

final readonly class CardPriceHistoryResponse
{
    /** @param list<PriceSnapshot> $snapshots */
    public function __construct(
        private string $cardId,
        private array $snapshots,
        private PriceRefreshStatus $refreshStatus,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $latestObservation = $this->snapshots[0] ?? null;
        $latestObservedOn = $latestObservation?->getObservedOn()->format('Y-m-d');
        $available = array_values(array_filter(
            $this->snapshots,
            static fn (PriceSnapshot $snapshot): bool => $snapshot->getVariant() !== PriceVariant::Unavailable,
        ));
        $byVariant = [];
        foreach ($available as $snapshot) {
            $byVariant[$snapshot->getVariant()->value][] = $snapshot;
        }

        $prices = [];
        foreach ([PriceVariant::Standard, PriceVariant::Foil] as $variant) {
            $history = $byVariant[$variant->value] ?? [];
            $latest = $history[0] ?? null;
            if (!$latest instanceof PriceSnapshot
                || $latestObservedOn === null
                || $latest->getObservedOn()->format('Y-m-d') !== $latestObservedOn) {
                continue;
            }

            $previous = $history[1] ?? null;
            $prices[] = [
                ...$this->snapshot($latest),
                'change' => $this->change($latest, $previous),
            ];
        }

        return [
            'cardId' => $this->cardId,
            'source' => RefreshCardPrices::SOURCE,
            'market' => RefreshCardPrices::MARKET,
            'currency' => RefreshCardPrices::CURRENCY,
            'refreshStatus' => $this->refreshStatus->value,
            'checkedAt' => $latestObservation?->getObservedAt()->format(DATE_ATOM),
            'prices' => $prices,
            'history' => array_map($this->snapshot(...), $available),
            'disclaimer' => 'Externe Cardmarket-prijsindicatie via TCGdex; dit is geen gegarandeerde verkoopopbrengst.',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(PriceSnapshot $snapshot): array
    {
        return [
            'variant' => $snapshot->getVariant()->value,
            'lowest' => $snapshot->getLowest()?->toArray(),
            'average' => $snapshot->getAverage()?->toArray(),
            'trend' => $snapshot->getTrend()?->toArray(),
            'averageOneDay' => $snapshot->getAverageOneDay()?->toArray(),
            'averageSevenDays' => $snapshot->getAverageSevenDays()?->toArray(),
            'averageThirtyDays' => $snapshot->getAverageThirtyDays()?->toArray(),
            'sourceUpdatedAt' => $snapshot->getSourceUpdatedAt()?->format(DATE_ATOM),
            'observedAt' => $snapshot->getObservedAt()->format(DATE_ATOM),
            'observedOn' => $snapshot->getObservedOn()->format('Y-m-d'),
        ];
    }

    /** @return array{amount: int, currency: string, percentage: float|null}|null */
    private function change(PriceSnapshot $latest, ?PriceSnapshot $previous): ?array
    {
        $latestTrend = $latest->getTrend();
        $previousTrend = $previous?->getTrend();
        if (!$latestTrend instanceof Money || !$previousTrend instanceof Money) {
            return null;
        }

        $difference = $latestTrend->amount - $previousTrend->amount;
        $percentage = $previousTrend->amount > 0
            ? round(($difference / $previousTrend->amount) * 100, 1)
            : null;

        return [
            'amount' => $difference,
            'currency' => $latestTrend->currency,
            'percentage' => $percentage,
        ];
    }
}
