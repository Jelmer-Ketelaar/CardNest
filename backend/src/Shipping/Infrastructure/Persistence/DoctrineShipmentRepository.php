<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Persistence;

use App\Order\Domain\Entity\Order;
use App\Shipping\Domain\Entity\Shipment;
use App\Shipping\Domain\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineShipmentRepository implements ShipmentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Shipment $shipment): void
    {
        $this->entityManager->persist($shipment);
    }

    public function getForOrder(Order $order): ?Shipment
    {
        return $this->entityManager->getRepository(Shipment::class)->findOneBy(['order' => $order]);
    }
}
