<?php

declare(strict_types=1);

namespace App\Collection\Domain\Entity;

use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * The private, owner-scoped aggregate that groups a collector's physical card
 * records. The MVP exposes one default collection per user, while this entity
 * provides a stable boundary for future collection views and settings.
 */
#[ORM\Entity]
#[ORM\Table(name: 'collections')]
#[ORM\UniqueConstraint(name: 'uniq_collections_owner', columns: ['owner_id'])]
final class Collection
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $owner,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
