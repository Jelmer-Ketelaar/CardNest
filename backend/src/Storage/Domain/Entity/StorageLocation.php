<?php

declare(strict_types=1);

namespace App\Storage\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Exception\InvalidStorageHierarchy;
use App\Storage\Domain\Exception\StorageOwnerMismatch;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'storage_locations')]
#[ORM\Index(name: 'idx_storage_locations_owner_parent', columns: ['owner_id', 'parent_id'])]
final class StorageLocation
{
    private const int MAXIMUM_DEPTH = 20;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $owner,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(length: 30, enumType: StorageLocationType::class)]
        private StorageLocationType $type,
        #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'RESTRICT')]
        private ?self $parent = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->assertValidParent($parent);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): StorageLocationType
    {
        return $this->type;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
        $this->touch();
    }

    public function changeType(StorageLocationType $type): void
    {
        $this->type = $type;
        $this->touch();
    }

    public function moveTo(?self $parent): void
    {
        $this->assertValidParent($parent);
        $this->parent = $parent;
        $this->touch();
    }

    /** @return list<StorageLocation> */
    public function path(): array
    {
        $path = [];
        $location = $this;
        $depth = 0;

        while ($location instanceof self) {
            array_unshift($path, $location);
            $location = $location->parent;
            ++$depth;
            if ($depth > self::MAXIMUM_DEPTH) {
                throw new InvalidStorageHierarchy('De opslaghiërarchie is te diep.');
            }
        }

        return $path;
    }

    private function assertValidParent(?self $parent): void
    {
        if (!$parent instanceof self) {
            return;
        }

        if (!$parent->owner->getId()->equals($this->owner->getId())) {
            throw new StorageOwnerMismatch('Een opslaglocatie kan alleen onder een eigen locatie worden geplaatst.');
        }

        $ancestor = $parent;
        $depth = 0;
        while ($ancestor instanceof self) {
            if ($ancestor->id->equals($this->id)) {
                throw new InvalidStorageHierarchy('Een opslaglocatie kan niet onder zichzelf worden geplaatst.');
            }

            $ancestor = $ancestor->parent;
            ++$depth;
            if ($depth > self::MAXIMUM_DEPTH) {
                throw new InvalidStorageHierarchy('De opslaghiërarchie is te diep.');
            }
        }
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidStorageHierarchy('Een opslaglocatie moet een naam hebben.');
        }

        return $name;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
