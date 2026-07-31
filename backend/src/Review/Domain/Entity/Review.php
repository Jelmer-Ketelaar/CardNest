<?php

declare(strict_types=1);

namespace App\Review\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Review\Domain\Exception\InvalidReview;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'reviews')]
#[ORM\UniqueConstraint(name: 'uniq_review_order_reviewer', columns: ['order_id', 'reviewer_id'])]
#[ORM\Index(name: 'idx_reviews_reviewed_created', columns: ['reviewed_user_id', 'created_at'])]
#[ORM\Index(name: 'idx_reviews_reviewer_created', columns: ['reviewer_id', 'created_at'])]
final class Review
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly Order $order,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'reviewer_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $reviewer,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'reviewed_user_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $reviewedUser,
        #[ORM\Column(name: 'overall_score')]
        private readonly int $overallScore,
        #[ORM\Column(name: 'description_accuracy_score')]
        private readonly int $descriptionAccuracyScore,
        #[ORM\Column(name: 'packaging_score')]
        private readonly int $packagingScore,
        #[ORM\Column(name: 'communication_score')]
        private readonly int $communicationScore,
        #[ORM\Column(name: 'shipping_speed_score')]
        private readonly int $shippingSpeedScore,
        ?string $comment = null,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($order->getStatus() !== OrderStatus::Completed) {
            throw new InvalidReview('Alleen een voltooide order kan worden beoordeeld.');
        }

        $reviewerIsBuyer = $order->getBuyer()->getId()->equals($reviewer->getId());
        $reviewerIsSeller = $order->getSeller()->getId()->equals($reviewer->getId());
        if (!$reviewerIsBuyer && !$reviewerIsSeller) {
            throw new InvalidReview('Alleen een orderdeelnemer kan een review plaatsen.');
        }

        $expectedReviewedUser = $reviewerIsBuyer ? $order->getSeller() : $order->getBuyer();
        if (!$expectedReviewedUser->getId()->equals($reviewedUser->getId())) {
            throw new InvalidReview('Een review moet over de andere orderdeelnemer gaan.');
        }
        if ($reviewer->getId()->equals($reviewedUser->getId())) {
            throw new InvalidReview('Een gebruiker kan zichzelf niet beoordelen.');
        }

        foreach ($this->scores() as $score) {
            if ($score < 1 || $score > 5) {
                throw new InvalidReview('Iedere reviewscore moet tussen 1 en 5 liggen.');
            }
        }

        $this->comment = self::normalizeComment($comment);
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getReviewer(): User
    {
        return $this->reviewer;
    }

    public function getReviewedUser(): User
    {
        return $this->reviewedUser;
    }

    public function getOverallScore(): int
    {
        return $this->overallScore;
    }

    public function getDescriptionAccuracyScore(): int
    {
        return $this->descriptionAccuracyScore;
    }

    public function getPackagingScore(): int
    {
        return $this->packagingScore;
    }

    public function getCommunicationScore(): int
    {
        return $this->communicationScore;
    }

    public function getShippingSpeedScore(): int
    {
        return $this->shippingSpeedScore;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<int> */
    private function scores(): array
    {
        return [
            $this->overallScore,
            $this->descriptionAccuracyScore,
            $this->packagingScore,
            $this->communicationScore,
            $this->shippingSpeedScore,
        ];
    }

    private static function normalizeComment(?string $comment): ?string
    {
        if ($comment === null || trim($comment) === '') {
            return null;
        }

        $comment = trim($comment);
        if (mb_strlen($comment) > 2000) {
            throw new InvalidReview('Een reviewtekst mag maximaal 2000 tekens bevatten.');
        }

        return $comment;
    }
}
