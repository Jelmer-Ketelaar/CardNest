<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_status_history')]
#[ORM\Index(name: 'idx_order_status_history_order_time', columns: ['order_id', 'occurred_at'])]
final class OrderStatusHistory
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'statusHistory')]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Order $order,
        #[ORM\Column(name: 'from_status', length: 30, nullable: true, enumType: OrderStatus::class)]
        private readonly ?OrderStatus $fromStatus,
        #[ORM\Column(name: 'to_status', length: 30, enumType: OrderStatus::class)]
        private readonly OrderStatus $toStatus,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'actor_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly User $actor,
        #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFromStatus(): ?OrderStatus
    {
        return $this->fromStatus;
    }

    public function getToStatus(): OrderStatus
    {
        return $this->toStatus;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
