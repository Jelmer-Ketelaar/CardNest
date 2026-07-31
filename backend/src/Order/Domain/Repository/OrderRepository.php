<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;

interface OrderRepository
{
    public function add(Order $order): void;

    /** @return list<Order> */
    public function findForParticipant(User $participant): array;

    public function getForParticipant(string $id, User $participant): ?Order;

    public function lockForSeller(string $id, User $seller): ?Order;

    public function lockForBuyer(string $id, User $buyer): ?Order;

    public function lockForParticipant(string $id, User $participant): ?Order;
}
