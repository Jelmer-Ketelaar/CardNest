<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Identity\Domain\Entity\User;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineWishlistRepository implements WishlistRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findAllForOwner(User $owner): array
    {
        /** @var list<Wishlist> $wishlists */
        $wishlists = $this->entityManager->createQueryBuilder()
            ->select('w', 'i', 'c', 's', 'v')
            ->from(Wishlist::class, 'w')
            ->leftJoin('w.items', 'i')
            ->leftJoin('i.catalogCard', 'c')
            ->leftJoin('c.set', 's')
            ->leftJoin('i.desiredVariant', 'v')
            ->where('w.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('w.name', 'ASC')
            ->addOrderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $wishlists;
    }

    public function getForOwner(string $id, User $owner): ?Wishlist
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var Wishlist|null $wishlist */
        $wishlist = $this->entityManager->createQueryBuilder()
            ->select('w', 'i', 'c', 's', 'v')
            ->from(Wishlist::class, 'w')
            ->leftJoin('w.items', 'i')
            ->leftJoin('i.catalogCard', 'c')
            ->leftJoin('c.set', 's')
            ->leftJoin('i.desiredVariant', 'v')
            ->where('w.id = :id')
            ->andWhere('w.owner = :owner')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $wishlist;
    }

    public function getItemForOwner(string $wishlistId, string $itemId, User $owner): ?WishlistItem
    {
        $wishlistUuid = $this->parseUuid($wishlistId);
        $itemUuid = $this->parseUuid($itemId);
        if (!$wishlistUuid instanceof Uuid || !$itemUuid instanceof Uuid) {
            return null;
        }

        /** @var WishlistItem|null $item */
        $item = $this->entityManager->createQueryBuilder()
            ->select('i', 'w', 'c', 's', 'v')
            ->from(WishlistItem::class, 'i')
            ->join('i.wishlist', 'w')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->leftJoin('i.desiredVariant', 'v')
            ->where('i.id = :itemId')
            ->andWhere('w.id = :wishlistId')
            ->andWhere('w.owner = :owner')
            ->setParameter('itemId', $itemUuid, UuidType::NAME)
            ->setParameter('wishlistId', $wishlistUuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $item;
    }

    public function findOpenForOwnerAndCard(User $owner, CatalogCard $card): array
    {
        /** @var list<WishlistItem> $items */
        $items = $this->entityManager->createQueryBuilder()
            ->select('i', 'w', 'v')
            ->from(WishlistItem::class, 'i')
            ->join('i.wishlist', 'w')
            ->leftJoin('i.desiredVariant', 'v')
            ->where('w.owner = :owner')
            ->andWhere('i.catalogCard = :card')
            ->andWhere('i.completedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('card', $card)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function save(Wishlist $wishlist): void
    {
        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();
    }

    public function remove(Wishlist $wishlist): void
    {
        $this->entityManager->remove($wishlist);
        $this->entityManager->flush();
    }

    public function removeItem(WishlistItem $item): void
    {
        $item->getWishlist()->removeItem($item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    private function parseUuid(string $value): ?Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
