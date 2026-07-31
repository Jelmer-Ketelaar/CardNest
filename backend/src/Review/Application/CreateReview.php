<?php

declare(strict_types=1);

namespace App\Review\Application;

use App\Identity\Domain\Entity\User;
use App\Order\Application\Exception\OrderNotFound;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Repository\OrderRepository;
use App\Review\Application\Dto\CreateReviewRequest;
use App\Review\Application\Dto\ReviewResponse;
use App\Review\Domain\Entity\Review;
use App\Review\Domain\Exception\ReviewAlreadyExists;
use App\Review\Domain\Repository\ReviewRepository;
use App\Shared\Domain\Transaction\TransactionManager;

final readonly class CreateReview
{
    public function __construct(
        private OrderRepository $orders,
        private ReviewRepository $reviews,
        private TransactionManager $transactions,
    ) {
    }

    public function create(User $reviewer, string $orderId, CreateReviewRequest $request): ReviewResponse
    {
        $review = $this->transactions->run(function () use ($reviewer, $orderId, $request): Review {
            $order = $this->orders->lockForParticipant($orderId, $reviewer);
            if (!$order instanceof Order) {
                throw new OrderNotFound('Order niet gevonden.');
            }
            if ($this->reviews->existsForOrderAndReviewer($order, $reviewer)) {
                throw new ReviewAlreadyExists('Je hebt deze order al beoordeeld.');
            }

            $reviewedUser = $order->getBuyer()->getId()->equals($reviewer->getId())
                ? $order->getSeller()
                : $order->getBuyer();
            $review = new Review(
                order: $order,
                reviewer: $reviewer,
                reviewedUser: $reviewedUser,
                overallScore: $request->overallScore,
                descriptionAccuracyScore: $request->descriptionAccuracyScore,
                packagingScore: $request->packagingScore,
                communicationScore: $request->communicationScore,
                shippingSpeedScore: $request->shippingSpeedScore,
                comment: $request->comment,
            );
            $this->reviews->add($review);

            return $review;
        });

        return new ReviewResponse($review, true);
    }
}
