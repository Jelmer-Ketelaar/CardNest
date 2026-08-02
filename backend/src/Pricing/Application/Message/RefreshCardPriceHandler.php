<?php

declare(strict_types=1);

namespace App\Pricing\Application\Message;

use App\Pricing\Application\RefreshCardPrices;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshCardPriceHandler
{
    public function __construct(private RefreshCardPrices $refresher)
    {
    }

    public function __invoke(RefreshCardPrice $message): void
    {
        $this->refresher->refresh($message->cardId);
    }
}
