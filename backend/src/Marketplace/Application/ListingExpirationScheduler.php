<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Marketplace\Application\Message\ExpireListing;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingStatus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class ListingExpirationScheduler
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function schedule(Listing $listing): void
    {
        $expiresAt = $listing->getExpiresAt();
        if ($listing->getStatus() !== ListingStatus::Active || $expiresAt === null) {
            return;
        }

        $delay = max(0, ($expiresAt->getTimestamp() - time()) * 1000);
        $this->messageBus->dispatch(
            new ExpireListing($listing->getId()->toRfc4122()),
            [new DelayStamp($delay)],
        );
    }
}
