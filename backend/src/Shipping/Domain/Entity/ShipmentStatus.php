<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Entity;

enum ShipmentStatus: string
{
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}
