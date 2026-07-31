<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;

final readonly class WishlistSummaryResponse
{
    public function __construct(private Wishlist $wishlist)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $desired = 0;
        $fulfilled = 0;
        $completedItems = 0;
        $sets = [];

        foreach ($this->wishlist->getItems() as $item) {
            $desired += $item->getDesiredQuantity();
            $fulfilled += $item->getFulfilledQuantity();
            $completedItems += $item->isCompleted() ? 1 : 0;
            $this->addSetProgress($sets, $item);
        }

        return [
            'id' => $this->wishlist->getId()->toRfc4122(),
            'name' => $this->wishlist->getName(),
            'itemCount' => $this->wishlist->getItems()->count(),
            'completedItemCount' => $completedItems,
            'progress' => $this->progress($desired, $fulfilled),
            'setProgress' => array_values(array_map(
                fn (array $set): array => [
                    ...$set,
                    'progress' => $this->progress($set['desiredQuantity'], $set['fulfilledQuantity']),
                ],
                $sets,
            )),
            'createdAt' => $this->wishlist->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->wishlist->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, array{id: string, name: string, desiredQuantity: int, fulfilledQuantity: int}> $sets
     */
    private function addSetProgress(array &$sets, WishlistItem $item): void
    {
        $set = $item->getCatalogCard()->getSet();
        $setId = $set->getId()->toRfc4122();
        $sets[$setId] ??= [
            'id' => $setId,
            'name' => $set->getName(),
            'desiredQuantity' => 0,
            'fulfilledQuantity' => 0,
        ];
        $sets[$setId]['desiredQuantity'] += $item->getDesiredQuantity();
        $sets[$setId]['fulfilledQuantity'] += $item->getFulfilledQuantity();
    }

    /** @return array{desiredQuantity: int, fulfilledQuantity: int, remainingQuantity: int, percentage: int} */
    private function progress(int $desired, int $fulfilled): array
    {
        return [
            'desiredQuantity' => $desired,
            'fulfilledQuantity' => $fulfilled,
            'remainingQuantity' => $desired - $fulfilled,
            'percentage' => $desired === 0 ? 0 : (int) round(($fulfilled / $desired) * 100),
        ];
    }
}
