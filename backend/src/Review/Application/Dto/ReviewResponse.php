<?php

declare(strict_types=1);

namespace App\Review\Application\Dto;

use App\Review\Domain\Entity\Review;

final readonly class ReviewResponse
{
    public function __construct(
        private Review $review,
        private bool $includeOrder = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $response = [
            'id' => $this->review->getId()->toRfc4122(),
            'reviewer' => ['id' => $this->review->getReviewer()->getId()->toRfc4122()],
            'reviewedUser' => ['id' => $this->review->getReviewedUser()->getId()->toRfc4122()],
            'scores' => [
                'overall' => $this->review->getOverallScore(),
                'descriptionAccuracy' => $this->review->getDescriptionAccuracyScore(),
                'packaging' => $this->review->getPackagingScore(),
                'communication' => $this->review->getCommunicationScore(),
                'shippingSpeed' => $this->review->getShippingSpeedScore(),
            ],
            'comment' => $this->review->getComment(),
            'createdAt' => $this->review->getCreatedAt()->format(DATE_ATOM),
        ];
        if ($this->includeOrder) {
            $response['orderId'] = $this->review->getOrder()->getId()->toRfc4122();
        }

        return $response;
    }
}
