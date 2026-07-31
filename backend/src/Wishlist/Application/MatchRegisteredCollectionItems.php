<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Collection\Application\RegisteredCollectionItemsProcessor;
use App\Identity\Domain\Entity\User;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class MatchRegisteredCollectionItems implements RegisteredCollectionItemsProcessor
{
    public function __construct(private WishlistRepository $wishlists)
    {
    }

    public function process(User $owner, array $items): void
    {
        $candidatesByCard = [];
        foreach ($items as $item) {
            $cardId = $item->getCatalogCard()->getId()->toRfc4122();
            $candidatesByCard[$cardId] ??= $this->wishlists->findOpenForOwnerAndCard(
                $owner,
                $item->getCatalogCard(),
            );

            foreach ($candidatesByCard[$cardId] as $candidate) {
                if ($candidate->matches($item)) {
                    $candidate->fulfill();
                }
            }
        }
    }
}
