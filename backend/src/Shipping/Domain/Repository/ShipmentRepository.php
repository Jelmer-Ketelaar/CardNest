<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Repository;

use App\Order\Domain\Entity\Order;
use App\Shipping\Domain\Entity\Shipment;

interface ShipmentRepository
{
    public function add(Shipment $shipment): void;

    public function getForOrder(Order $order): ?Shipment;
}
