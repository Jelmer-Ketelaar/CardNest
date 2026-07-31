<?php

declare(strict_types=1);

namespace App\Review\Application;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Review\Application\Dto\ListUserReviewsRequest;
use App\Review\Application\Dto\ReputationResponse;
use App\Review\Application\Dto\ReviewCollectionResponse;
use App\Review\Application\Exception\ReviewedUserNotFound;
use App\Review\Domain\Repository\ReviewRepository;

final readonly class FindReviews
{
    public function __construct(
        private ReviewRepository $reviews,
        private UserRepository $users,
    ) {
    }

    public function authored(User $reviewer): ReviewCollectionResponse
    {
        return new ReviewCollectionResponse($this->reviews->findAuthoredBy($reviewer), true);
    }

    public function reputation(string $userId, ListUserReviewsRequest $request): ReputationResponse
    {
        $reviewedUser = $this->users->findById($userId);
        if (!$reviewedUser instanceof User) {
            throw new ReviewedUserNotFound('Gebruiker niet gevonden.');
        }

        return new ReputationResponse(
            $reviewedUser,
            $this->reviews->reputationFor($reviewedUser),
            $this->reviews->searchForReviewedUser($reviewedUser, $request->page, $request->perPage),
        );
    }
}
