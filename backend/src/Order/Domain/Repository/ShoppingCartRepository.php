<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\ShoppingCart;

interface ShoppingCartRepository
{
    public function getForBuyer(User $buyer): ?ShoppingCart;

    public function lockForCheckout(User $buyer): ?ShoppingCart;

    public function save(ShoppingCart $cart): void;
}
