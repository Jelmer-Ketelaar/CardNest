<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Application\Dto\UpdateListingRequest;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class UpdateListing
{
    public function __construct(
        private ListingResolver $resolver,
        private ListingRepository $listings,
        private ListingExpirationScheduler $expirationScheduler,
    ) {
    }

    public function update(User $seller, string $id, UpdateListingRequest $request): ListingResponse
    {
        $listing = $this->resolver->owned($id, $seller);
        $expirationChanged = false;
        if ($request->price !== null && $request->currency !== null) {
            $listing->changePrice(new Money($request->price, $request->currency));
        }
        if ($request->clearDescription) {
            $listing->changeDescription(null);
        } elseif ($request->description !== null) {
            $listing->changeDescription($request->description);
        }
        if ($request->frontImageUrl !== null && $request->backImageUrl !== null) {
            $listing->replacePhotos([
                new ListingPhotoReference(ListingImageSide::Front, $request->frontImageUrl),
                new ListingPhotoReference(ListingImageSide::Back, $request->backImageUrl),
            ]);
        }
        if ($request->clearExpiration) {
            $listing->changeExpiration(null);
        } elseif ($request->expiresAt !== null) {
            $listing->changeExpiration(new DateTimeImmutable($request->expiresAt));
            $expirationChanged = true;
        }

        $this->listings->save($listing);
        if ($expirationChanged) {
            $this->expirationScheduler->schedule($listing);
        }

        return new ListingResponse($listing, true);
    }
}
