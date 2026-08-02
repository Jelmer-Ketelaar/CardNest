<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Message;

use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class ExpireListingHandler
{
    public function __construct(
        private ListingRepository $listings,
        private TransactionManager $transactions,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function __invoke(ExpireListing $message): void
    {
        $this->transactions->run(function () use ($message): void {
            $listing = $this->listings->lockById($message->listingId);
            if (!$listing instanceof Listing || $listing->getStatus() !== ListingStatus::Active) {
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
        });
    }
}
