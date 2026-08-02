<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Message;

use App\Pricing\Application\Message\QueueDailyPriceRefresh;
use App\Pricing\Application\Message\QueueDailyPriceRefreshHandler;
use App\Pricing\Application\Message\RefreshCardPrice;
use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class QueueDailyPriceRefreshHandlerTest extends TestCase
{
    public function testItDispatchesOneRefreshMessageForEveryTrackedCard(): void
    {
        // Arrange
        $trackedCards = $this->createMock(TrackedPriceCardRepository::class);
        $trackedCards->expects(self::once())->method('cardIds')->willReturn(['card-a', 'card-b']);
        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });
        $handler = new QueueDailyPriceRefreshHandler($trackedCards, $messageBus);

        // Act
        $handler(new QueueDailyPriceRefresh());

        // Assert
        self::assertContainsOnlyInstancesOf(RefreshCardPrice::class, $dispatched);
        self::assertSame(['card-a', 'card-b'], array_map(
            static fn (RefreshCardPrice $message): string => $message->cardId,
            $dispatched,
        ));
    }
}
