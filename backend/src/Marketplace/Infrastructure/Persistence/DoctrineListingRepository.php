<?php

declare(strict_types=1);

namespace App\Marketplace\Infrastructure\Persistence;

use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\ListingAlreadyExists;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\Repository\ListingSearchCriteria;
use App\Marketplace\Domain\Repository\ListingSearchResult;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineListingRepository implements ListingRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function searchActive(ListingSearchCriteria $criteria): ListingSearchResult
    {
        $query = $this->activeQuery($criteria)
            ->select('l', 'i', 'c', 's', 'v', 'seller', 'image', 'shipping')
            ->orderBy('l.publishedAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery();
        $paginator = new Paginator($query, true);

        /** @var list<Listing> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new ListingSearchResult($items, count($paginator), $criteria->page, $criteria->perPage);
    }

    public function findAllForSeller(User $seller): array
    {
        /** @var list<Listing> $listings */
        $listings = $this->detailQuery()
            ->where('l.seller = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $listings;
    }

    public function get(string $id): ?Listing
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var Listing|null $listing */
        $listing = $this->detailQuery()
            ->where('l.id = :id')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $listing;
    }

    public function getActive(string $id): ?Listing
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var Listing|null $listing */
        $listing = $this->detailQuery()
            ->where('l.id = :id')
            ->andWhere('l.status = :status')
            ->andWhere('(l.expiresAt IS NULL OR l.expiresAt > :now)')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('status', ListingStatus::Active)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        return $listing;
    }

    public function getForSeller(string $id, User $seller): ?Listing
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var Listing|null $listing */
        $listing = $this->detailQuery()
            ->where('l.id = :id')
            ->andWhere('l.seller = :seller')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('seller', $seller)
            ->getQuery()
            ->getOneOrNullResult();

        return $listing;
    }

    public function lockForCheckout(array $ids): array
    {
        sort($ids);
        $listings = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $uuid = $this->parseUuid($id);
            if (!$uuid instanceof Uuid) {
                continue;
            }

            $listing = $this->entityManager->find(Listing::class, $uuid);
            if (!$listing instanceof Listing) {
                continue;
            }

            // Refresh after acquiring the row lock so a cart loaded just before
            // another checkout cannot continue with a stale active status.
            $this->entityManager->refresh($listing, LockMode::PESSIMISTIC_WRITE);
            $listings[] = $listing;
        }

        return $listings;
    }

    public function hasBlockingListing(CollectionItem $item, ?Listing $except = null): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(Listing::class, 'l')
            ->where('l.collectionItem = :item')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('item', $item)
            ->setParameter('statuses', [
                ListingStatus::PendingReview,
                ListingStatus::Active,
                ListingStatus::Reserved,
                ListingStatus::Sold,
            ]);
        if ($except instanceof Listing) {
            $query->andWhere('l != :except')->setParameter('except', $except);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }

    public function save(Listing $listing): void
    {
        try {
            $this->entityManager->persist($listing);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ListingAlreadyExists('Deze fysieke kaart heeft al een openstaande of verkochte listing.', 0, $exception);
        }
    }

    private function activeQuery(ListingSearchCriteria $criteria): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Listing::class, 'l')
            ->join('l.collectionItem', 'i')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->join('i.variant', 'v')
            ->join('l.seller', 'seller')
            ->leftJoin('l.images', 'image')
            ->leftJoin('l.shippingMethods', 'shipping')
            ->where('l.status = :active')
            ->andWhere('(l.expiresAt IS NULL OR l.expiresAt > :now)')
            ->setParameter('active', ListingStatus::Active)
            ->setParameter('now', new DateTimeImmutable());

        $this->applyUuidFilter($query, 'c.id', 'cardId', $criteria->cardId);
        $this->applyUuidFilter($query, 's.id', 'setId', $criteria->setId);
        if ($criteria->cardName !== null && trim($criteria->cardName) !== '') {
            $query->andWhere('LOWER(c.name) LIKE :name')
                ->setParameter('name', '%'.mb_strtolower(trim($criteria->cardName)).'%');
        }
        if ($criteria->cardNumber !== null && trim($criteria->cardNumber) !== '') {
            $query->andWhere('LOWER(c.number) LIKE :number')
                ->setParameter('number', '%'.mb_strtolower(trim($criteria->cardNumber)).'%');
        }
        if ($criteria->language !== null) {
            $query->andWhere('i.language = :language')->setParameter('language', $criteria->language);
        }
        if ($criteria->variant !== null) {
            $query->andWhere('v.code = :variant')->setParameter('variant', $criteria->variant);
        }
        if ($criteria->condition !== null) {
            $query->andWhere('i.condition = :condition')->setParameter('condition', $criteria->condition);
        }
        if ($criteria->minimumPrice !== null) {
            $query->andWhere('l.priceAmount >= :minimumPrice')->setParameter('minimumPrice', $criteria->minimumPrice);
        }
        if ($criteria->maximumPrice !== null) {
            $query->andWhere('l.priceAmount <= :maximumPrice')->setParameter('maximumPrice', $criteria->maximumPrice);
        }
        if ($criteria->pickupAllowed) {
            $query
                ->join('l.shippingMethods', 'shippingFilter')
                ->andWhere('shippingFilter.code = :pickup')
                ->setParameter('pickup', ShippingMethodCode::Pickup);
        } elseif ($criteria->shippingMethod instanceof ShippingMethodCode) {
            $query
                ->join('l.shippingMethods', 'shippingFilter')
                ->andWhere('shippingFilter.code = :shippingMethod')
                ->setParameter('shippingMethod', $criteria->shippingMethod);
        }

        return $query;
    }

    private function detailQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l', 'i', 'c', 's', 'v', 'seller', 'image', 'shipping', 'history')
            ->from(Listing::class, 'l')
            ->join('l.collectionItem', 'i')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->join('i.variant', 'v')
            ->join('l.seller', 'seller')
            ->leftJoin('l.images', 'image')
            ->leftJoin('l.shippingMethods', 'shipping')
            ->leftJoin('l.priceHistory', 'history');
    }

    private function applyUuidFilter(QueryBuilder $query, string $field, string $parameter, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $uuid = $this->parseUuid($value);
        if (!$uuid instanceof Uuid) {
            $query->andWhere('1 = 0');

            return;
        }

        $query->andWhere($field.' = :'.$parameter)->setParameter($parameter, $uuid, UuidType::NAME);
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
