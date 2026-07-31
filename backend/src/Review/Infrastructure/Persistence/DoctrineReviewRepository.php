<?php

declare(strict_types=1);

namespace App\Review\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Review\Domain\Entity\Review;
use App\Review\Domain\Repository\ReputationSummary;
use App\Review\Domain\Repository\ReviewRepository;
use App\Review\Domain\Repository\ReviewSearchResult;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineReviewRepository implements ReviewRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Review $review): void
    {
        $this->entityManager->persist($review);
    }

    public function existsForOrderAndReviewer(Order $order, User $reviewer): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(review.id)')
            ->from(Review::class, 'review')
            ->where('review.order = :order')
            ->andWhere('review.reviewer = :reviewer')
            ->setParameter('order', $order)
            ->setParameter('reviewer', $reviewer)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function findAuthoredBy(User $reviewer): array
    {
        /** @var list<Review> $reviews */
        $reviews = $this->entityManager->createQueryBuilder()
            ->select('review', 'purchase', 'reviewedUser')
            ->from(Review::class, 'review')
            ->join('review.order', 'purchase')
            ->join('review.reviewedUser', 'reviewedUser')
            ->where('review.reviewer = :reviewer')
            ->setParameter('reviewer', $reviewer)
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $reviews;
    }

    public function searchForReviewedUser(User $reviewedUser, int $page, int $perPage): ReviewSearchResult
    {
        $total = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(review.id)')
            ->from(Review::class, 'review')
            ->where('review.reviewedUser = :reviewedUser')
            ->setParameter('reviewedUser', $reviewedUser)
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Review> $reviews */
        $reviews = $this->entityManager->createQueryBuilder()
            ->select('review', 'reviewer')
            ->from(Review::class, 'review')
            ->join('review.reviewer', 'reviewer')
            ->where('review.reviewedUser = :reviewedUser')
            ->setParameter('reviewedUser', $reviewedUser)
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new ReviewSearchResult($reviews, $total, $page, $perPage);
    }

    public function reputationFor(User $reviewedUser): ReputationSummary
    {
        /** @var array<string, int|float|string|null>|false $row */
        $row = $this->entityManager->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT COUNT(*) AS review_count,
                       AVG(overall_score) AS average_overall,
                       AVG(description_accuracy_score) AS average_description_accuracy,
                       AVG(packaging_score) AS average_packaging,
                       AVG(communication_score) AS average_communication,
                       AVG(shipping_speed_score) AS average_shipping_speed
                FROM reviews
                WHERE reviewed_user_id = :reviewed_user_id
                SQL,
            ['reviewed_user_id' => $reviewedUser->getId()->toRfc4122()],
            ['reviewed_user_id' => ParameterType::STRING],
        );

        if ($row === false) {
            return new ReputationSummary(0, null, null, null, null, null);
        }

        return new ReputationSummary(
            (int) $row['review_count'],
            $this->average($row['average_overall']),
            $this->average($row['average_description_accuracy']),
            $this->average($row['average_packaging']),
            $this->average($row['average_communication']),
            $this->average($row['average_shipping_speed']),
        );
    }

    private function average(int|float|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
