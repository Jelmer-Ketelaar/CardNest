<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Exception\InvalidListingTransition;
use App\Marketplace\Domain\Repository\ListingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class CancelListing
{
    public function __construct(
        private ListingResolver $resolver,
        private ListingRepository $listings,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function cancel(User $seller, string $id): ListingResponse
    {
        $listing = $this->resolver->owned($id, $seller);
        $listing->assertCancellable();
        if (!$this->workflow->can($listing, 'cancel')) {
            throw new InvalidListingTransition('Deze listing kan niet worden geannuleerd.');
        }

        $wasActive = $listing->getStatus() === ListingStatus::Active;
        $this->workflow->apply($listing, 'cancel');
        if ($wasActive) {
            $listing->getCollectionItem()->removeFromSale();
        }
        $this->listings->save($listing);

        return new ListingResponse($listing, true);
    }
}
