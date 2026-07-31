<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderPage;
use App\Binder\Domain\Entity\BinderPageSide;
use App\Binder\Domain\Entity\BinderSlot;
use App\Collection\Domain\Entity\CollectionItem;

final readonly class BinderDetailResponse
{
    /** @param array<string, CollectionItem> $itemsBySlot */
    public function __construct(
        private Binder $binder,
        private array $itemsBySlot,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $pages = $this->binder->getPages()->toArray();
        usort($pages, static function (BinderPage $left, BinderPage $right): int {
            $pageComparison = $left->getPageNumber() <=> $right->getPageNumber();
            if ($pageComparison !== 0) {
                return $pageComparison;
            }

            return ($left->getSide() === BinderPageSide::Front ? 0 : 1)
                <=> ($right->getSide() === BinderPageSide::Front ? 0 : 1);
        });

        return [
            ...(new BinderSummaryResponse($this->binder, count($this->itemsBySlot)))->toArray(),
            'pages' => array_map($this->page(...), $pages),
        ];
    }

    /** @return array<string, mixed> */
    private function page(BinderPage $page): array
    {
        return [
            'id' => $page->getId()->toRfc4122(),
            'number' => $page->getPageNumber(),
            'side' => $page->getSide()->value,
            'slots' => array_map($this->slot(...), $page->getSlots()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    private function slot(BinderSlot $slot): array
    {
        $item = $this->itemsBySlot[$slot->getId()->toRfc4122()] ?? null;
        $card = $item?->getCatalogCard();
        $reserved = $slot->getReservedCatalogCard();

        return [
            'id' => $slot->getId()->toRfc4122(),
            'position' => $slot->getPosition(),
            'row' => intdiv($slot->getPosition() - 1, $this->binder->getColumns()) + 1,
            'column' => (($slot->getPosition() - 1) % $this->binder->getColumns()) + 1,
            'collectionItem' => $item instanceof CollectionItem ? [
                'id' => $item->getId()->toRfc4122(),
                'card' => [
                    'id' => $card?->getId()->toRfc4122(),
                    'name' => $card?->getName(),
                    'number' => $card?->getNumber(),
                    'thumbnailUrl' => $card?->getThumbnailUrl(),
                ],
                'language' => $item->getLanguage()->value,
                'variant' => $item->getVariant()->getCode()->value,
                'condition' => $item->getCondition()->value,
            ] : null,
            'placeholder' => $reserved !== null || $slot->getPlaceholderLabel() !== null ? [
                'catalogCard' => $reserved !== null ? [
                    'id' => $reserved->getId()->toRfc4122(),
                    'name' => $reserved->getName(),
                    'number' => $reserved->getNumber(),
                    'thumbnailUrl' => $reserved->getThumbnailUrl(),
                ] : null,
                'label' => $slot->getPlaceholderLabel(),
            ] : null,
        ];
    }
}
