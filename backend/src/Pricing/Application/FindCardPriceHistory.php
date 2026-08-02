<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Pricing\Application\Dto\CardPriceHistoryResponse;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final readonly class FindCardPriceHistory
{
    private const int FAILURE_COOLDOWN_SECONDS = 300;

    public function __construct(
        private CatalogCardRepository $catalogCards,
        private PriceSnapshotRepository $snapshots,
        private RefreshCardPrices $refresher,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function find(string $cardId, int $days): ?CardPriceHistoryResponse
    {
        $card = $this->catalogCards->get($cardId);
        if (!$card instanceof CatalogCard) {
            return null;
        }

        $failureKey = 'pricing-failure-'.hash('sha256', $cardId);
        $failure = $this->cache->getItem($failureKey);
        if ($failure->isHit()) {
            $refreshStatus = PriceRefreshStatus::RefreshFailed;
        } else {
            try {
                $refreshStatus = $this->refresher->refresh($cardId);
            } catch (PriceProviderException $exception) {
                $failure->set(true)->expiresAfter(self::FAILURE_COOLDOWN_SECONDS);
                $this->cache->save($failure);
                $this->logger->warning('A current market price could not be retrieved.', [
                    'catalogCardId' => $cardId,
                    'source' => RefreshCardPrices::SOURCE,
                    'exception' => $exception,
                ]);
                $refreshStatus = PriceRefreshStatus::RefreshFailed;
            }
        }

        $since = new DateTimeImmutable(sprintf('-%d days', $days - 1));
        $history = $this->snapshots->history(
            $card,
            RefreshCardPrices::SOURCE,
            RefreshCardPrices::MARKET,
            $since,
        );

        return new CardPriceHistoryResponse($cardId, $history, $refreshStatus);
    }
}
