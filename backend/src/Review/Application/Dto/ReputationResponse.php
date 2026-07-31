<?php

declare(strict_types=1);

namespace App\Review\Application\Dto;

use App\Identity\Domain\Entity\User;
use App\Review\Domain\Entity\Review;
use App\Review\Domain\Repository\ReputationSummary;
use App\Review\Domain\Repository\ReviewSearchResult;

final readonly class ReputationResponse
{
    public function __construct(
        private User $user,
        private ReputationSummary $summary,
        private ReviewSearchResult $reviews,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => [
                'id' => $this->user->getId()->toRfc4122(),
                'memberSince' => $this->user->getCreatedAt()->format(DATE_ATOM),
            ],
            'summary' => [
                'reviewCount' => $this->summary->reviewCount,
                'averageOverall' => $this->summary->averageOverall,
                'averages' => [
                    'descriptionAccuracy' => $this->summary->averageDescriptionAccuracy,
                    'packaging' => $this->summary->averagePackaging,
                    'communication' => $this->summary->averageCommunication,
                    'shippingSpeed' => $this->summary->averageShippingSpeed,
                ],
            ],
            'reviews' => [
                'items' => array_map(
                    static fn (Review $review): array => (new ReviewResponse($review))->toArray(),
                    $this->reviews->items,
                ),
                'page' => $this->reviews->page,
                'perPage' => $this->reviews->perPage,
                'total' => $this->reviews->total,
                'totalPages' => $this->reviews->total === 0
                    ? 0
                    : (int) ceil($this->reviews->total / $this->reviews->perPage),
            ],
        ];
    }
}
