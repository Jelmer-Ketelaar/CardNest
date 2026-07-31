<?php

declare(strict_types=1);

namespace App\Binder\Domain\Entity;

use App\Binder\Domain\Exception\InvalidBinderConfiguration;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Exception\StorageOwnerMismatch;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'binders')]
#[ORM\Index(name: 'idx_binders_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_binders_parent_location', columns: ['parent_location_id'])]
final class Binder
{
    private const int MAXIMUM_SLOTS = 2000;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, BinderPage> */
    #[ORM\OneToMany(mappedBy: 'binder', targetEntity: BinderPage::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['pageNumber' => 'ASC', 'side' => 'ASC'])]
    private Collection $pages;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
        private readonly User $owner,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(name: 'grid_rows')]
        private readonly int $rows,
        #[ORM\Column(name: 'grid_columns')]
        private readonly int $columns,
        #[ORM\Column(name: 'page_count')]
        private readonly int $pageCount,
        #[ORM\Column(name: 'double_sided')]
        private readonly bool $doubleSided,
        #[ORM\Column(name: 'layout_strategy', length: 30, enumType: BinderLayoutStrategy::class)]
        private BinderLayoutStrategy $layoutStrategy = BinderLayoutStrategy::Custom,
        #[ORM\ManyToOne(targetEntity: StorageLocation::class)]
        #[ORM\JoinColumn(name: 'parent_location_id', nullable: true, onDelete: 'RESTRICT')]
        private ?StorageLocation $parentLocation = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->name = self::normalizeName($name);
        $this->assertConfiguration();
        $this->assertParentOwner($parentLocation);
        $this->pages = new ArrayCollection();
        $this->createPages();
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

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function isDoubleSided(): bool
    {
        return $this->doubleSided;
    }

    public function getLayoutStrategy(): BinderLayoutStrategy
    {
        return $this->layoutStrategy;
    }

    public function getParentLocation(): ?StorageLocation
    {
        return $this->parentLocation;
    }

    /** @return Collection<int, BinderPage> */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function getSurfaceCount(): int
    {
        return $this->pageCount * ($this->doubleSided ? 2 : 1);
    }

    public function getSlotCount(): int
    {
        return $this->getSurfaceCount() * $this->rows * $this->columns;
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

    public function changeLayoutStrategy(BinderLayoutStrategy $layoutStrategy): void
    {
        $this->layoutStrategy = $layoutStrategy;
        $this->touch();
    }

    public function moveTo(?StorageLocation $parentLocation): void
    {
        $this->assertParentOwner($parentLocation);
        $this->parentLocation = $parentLocation;
        $this->touch();
    }

    private function createPages(): void
    {
        for ($pageNumber = 1; $pageNumber <= $this->pageCount; ++$pageNumber) {
            $this->pages->add(new BinderPage($this, $pageNumber, BinderPageSide::Front, $this->rows, $this->columns));
            if ($this->doubleSided) {
                $this->pages->add(new BinderPage($this, $pageNumber, BinderPageSide::Back, $this->rows, $this->columns));
            }
        }
    }

    private function assertConfiguration(): void
    {
        if ($this->rows < 1 || $this->rows > 6 || $this->columns < 1 || $this->columns > 6) {
            throw new InvalidBinderConfiguration('Een binderpagina moet tussen 1×1 en 6×6 vakken bevatten.');
        }

        if ($this->pageCount < 1 || $this->pageCount > 100) {
            throw new InvalidBinderConfiguration('Een binder moet tussen 1 en 100 fysieke pagina’s bevatten.');
        }

        if ($this->getSlotCount() > self::MAXIMUM_SLOTS) {
            throw new InvalidBinderConfiguration('Een binder kan maximaal 2000 vakken bevatten.');
        }
    }

    private function assertParentOwner(?StorageLocation $parentLocation): void
    {
        if ($parentLocation instanceof StorageLocation
            && !$parentLocation->getOwner()->getId()->equals($this->owner->getId())) {
            throw new StorageOwnerMismatch('Een binder kan alleen in een eigen opslaglocatie worden geplaatst.');
        }
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidBinderConfiguration('Een binder moet een naam hebben.');
        }

        return $name;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
