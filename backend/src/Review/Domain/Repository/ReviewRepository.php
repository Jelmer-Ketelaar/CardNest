<?php

declare(strict_types=1);

namespace App\Review\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Review\Domain\Entity\Review;

interface ReviewRepository
{
    public function add(Review $review): void;

    public function existsForOrderAndReviewer(Order $order, User $reviewer): bool;

    /** @return list<Review> */
    public function findAuthoredBy(User $reviewer): array;

    public function searchForReviewedUser(User $reviewedUser, int $page, int $perPage): ReviewSearchResult;

    public function reputationFor(User $reviewedUser): ReputationSummary;
}
