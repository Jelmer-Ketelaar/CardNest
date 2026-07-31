<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Order\Domain\ValueObject\ShippingAddress;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_addresses')]
#[ORM\UniqueConstraint(name: 'uniq_order_shipping_address', columns: ['order_id'])]
final class OrderAddress
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Order::class, inversedBy: 'shippingAddress')]
        #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Order $order,
        ShippingAddress $address,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->fullName = $address->fullName;
        $this->addressLine1 = $address->addressLine1;
        $this->addressLine2 = $address->addressLine2;
        $this->postalCode = $address->postalCode;
        $this->city = $address->city;
        $this->countryCode = $address->countryCode;
    }

    #[ORM\Column(name: 'full_name', length: 180)]
    private readonly string $fullName;

    #[ORM\Column(name: 'address_line_1', length: 180)]
    private readonly string $addressLine1;

    #[ORM\Column(name: 'address_line_2', length: 180, nullable: true)]
    private readonly ?string $addressLine2;

    #[ORM\Column(name: 'postal_code', length: 20)]
    private readonly string $postalCode;

    #[ORM\Column(length: 120)]
    private readonly string $city;

    #[ORM\Column(name: 'country_code', length: 2)]
    private readonly string $countryCode;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
