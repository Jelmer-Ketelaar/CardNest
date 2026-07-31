<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use App\Binder\Domain\Entity\Binder;

final readonly class BinderCollectionResponse
{
    /**
     * @param list<Binder>       $binders
     * @param array<string, int> $occupiedCounts
     */
    public function __construct(
        private array $binders,
        private array $occupiedCounts,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (Binder $binder): array => (new BinderSummaryResponse(
                    $binder,
                    $this->occupiedCounts[$binder->getId()->toRfc4122()] ?? 0,
                ))->toArray(),
                $this->binders,
            ),
            'total' => count($this->binders),
        ];
    }
}
