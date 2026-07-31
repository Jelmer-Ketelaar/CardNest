<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Domain\Exception\InvalidListingTransition;
use App\Marketplace\Domain\Exception\ListingAlreadyExists;
use App\Marketplace\Domain\Repository\ListingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class PublishListing
{
    public function __construct(
        private ListingResolver $resolver,
        private ListingRepository $listings,
        private ListingExpirationScheduler $expirationScheduler,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $workflow,
    ) {
    }

    public function publish(User $seller, string $id): ListingResponse
    {
        $listing = $this->resolver->owned($id, $seller);
        if ($this->listings->hasBlockingListing($listing->getCollectionItem(), $listing)) {
            throw new ListingAlreadyExists('Deze fysieke kaart heeft al een openstaande of verkochte listing.');
        }
        $listing->assertPublishable();
        if (!$this->workflow->can($listing, 'publish')) {
            throw new InvalidListingTransition('Deze listing kan niet worden gepubliceerd.');
        }

        $this->workflow->apply($listing, 'publish');
        $listing->getCollectionItem()->markForSale();
        $this->listings->save($listing);
        $this->expirationScheduler->schedule($listing);

        return new ListingResponse($listing, true);
    }
}
