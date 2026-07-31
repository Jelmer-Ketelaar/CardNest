<?php

declare(strict_types=1);

namespace App\Binder\Domain\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'binder_pages')]
#[ORM\UniqueConstraint(name: 'uniq_binder_page_side', columns: ['binder_id', 'page_number', 'side'])]
final class BinderPage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, BinderSlot> */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: BinderSlot::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $slots;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Binder::class, inversedBy: 'pages')]
        #[ORM\JoinColumn(name: 'binder_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Binder $binder,
        #[ORM\Column(name: 'page_number')]
        private readonly int $pageNumber,
        #[ORM\Column(length: 10, enumType: BinderPageSide::class)]
        private readonly BinderPageSide $side,
        int $rows,
        int $columns,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->slots = new ArrayCollection();
        for ($position = 1; $position <= $rows * $columns; ++$position) {
            $this->slots->add(new BinderSlot($this, $position, $createdAt));
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBinder(): Binder
    {
        return $this->binder;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function getSide(): BinderPageSide
    {
        return $this->side;
    }

    /** @return Collection<int, BinderSlot> */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
