<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Message;

use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Repository\ListingRepository;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class ExpireListingHandler
{
    public function __construct(
        private ListingRepository $listings,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function __invoke(ExpireListing $message): void
    {
        $listing = $this->listings->get($message->listingId);
        if ($listing === null || $listing->getStatus() !== ListingStatus::Active) {
            return;
        }

        $expiresAt = $listing->getExpiresAt();
        if ($expiresAt === null || $expiresAt > new DateTimeImmutable()) {
            return;
        }

        if (!$this->workflow->can($listing, 'expire')) {
            return;
        }

        $this->workflow->apply($listing, 'expire');
        $listing->getCollectionItem()->removeFromSale();
        $this->listings->save($listing);
    }
}
