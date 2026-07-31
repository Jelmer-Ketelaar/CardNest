<?php

declare(strict_types=1);

namespace App\Review\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateReviewRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 5)]
        public int $overallScore,
        #[Assert\Range(min: 1, max: 5)]
        public int $descriptionAccuracyScore,
        #[Assert\Range(min: 1, max: 5)]
        public int $packagingScore,
        #[Assert\Range(min: 1, max: 5)]
        public int $communicationScore,
        #[Assert\Range(min: 1, max: 5)]
        public int $shippingSpeedScore,
        #[Assert\Length(max: 2000)]
        public ?string $comment = null,
    ) {
    }
}
