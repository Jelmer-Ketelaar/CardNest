<?php

declare(strict_types=1);

namespace App\Pricing\Application\Message;

use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class QueueDailyPriceRefreshHandler
{
    public function __construct(
        private TrackedPriceCardRepository $trackedCards,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(QueueDailyPriceRefresh $message): void
    {
        foreach ($this->trackedCards->cardIds() as $cardId) {
            $this->messageBus->dispatch(new RefreshCardPrice($cardId));
        }
    }
}
