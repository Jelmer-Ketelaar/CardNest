<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Collection\Domain\Repository\CollectionItemSearchCriteria;
use App\Collection\Domain\Repository\CollectionItemSearchResult;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineCollectionItemRepository implements CollectionItemRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function search(User $owner, CollectionItemSearchCriteria $criteria): CollectionItemSearchResult
    {
        $total = (int) $this->baseQuery($owner, $criteria)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<CollectionItem> $items */
        $items = $this->baseQuery($owner, $criteria)
            ->select('i', 'c', 's', 'v')
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new CollectionItemSearchResult($items, $total, $criteria->page, $criteria->perPage);
    }

    public function getForOwner(string $id, User $owner): ?CollectionItem
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var CollectionItem|null $item */
        $item = $this->entityManager->createQueryBuilder()
            ->select('i', 'c', 's', 'v')
            ->from(CollectionItem::class, 'i')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->join('i.variant', 'v')
            ->where('i.id = :id')
            ->andWhere('i.owner = :owner')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $item;
    }

    public function saveMany(array $items): void
    {
        $this->entityManager->wrapInTransaction(function () use ($items): void {
            foreach ($items as $item) {
                $this->entityManager->persist($item);
            }
        });
    }

    public function save(CollectionItem $item): void
    {
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    public function duplicateCounts(User $owner): array
    {
        /** @var list<array{card_id: string, language: string, variant_id: string, item_count: int|string}> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT catalog_card_id::text AS card_id,
                       language,
                       variant_id::text AS variant_id,
                       COUNT(*) AS item_count
                FROM collection_items
                WHERE owner_id = :owner_id AND status = :status
                GROUP BY catalog_card_id, language, variant_id
                SQL,
            [
                'owner_id' => $owner->getId()->toRfc4122(),
                'status' => CollectionItemStatus::InCollection->value,
            ],
            ['owner_id' => ParameterType::STRING, 'status' => ParameterType::STRING],
        );

        $counts = [];
        foreach ($rows as $row) {
            $key = CollectionItem::buildDuplicateKey($row['card_id'], $row['language'], $row['variant_id']);
            $counts[$key] = (int) $row['item_count'];
        }

        return $counts;
    }

    public function countMatching(
        User $owner,
        CatalogCard $card,
        ?CardLanguage $language,
        ?CardVariant $variant,
        ?CardCondition $minimumCondition,
        ?Money $maximumPrice,
    ): int {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(CollectionItem::class, 'i')
            ->where('i.owner = :owner')
            ->andWhere('i.catalogCard = :card')
            ->andWhere('i.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('card', $card)
            ->setParameter('status', CollectionItemStatus::InCollection);

        if ($language instanceof CardLanguage) {
            $query->andWhere('i.language = :language')->setParameter('language', $language);
        }
        if ($variant instanceof CardVariant) {
            $query->andWhere('i.variant = :variant')->setParameter('variant', $variant);
        }
        if ($minimumCondition instanceof CardCondition) {
            $query
                ->andWhere('i.condition IN (:conditions)')
                ->setParameter('conditions', CardCondition::valuesMeetingMinimum($minimumCondition));
        }
        if ($maximumPrice instanceof Money) {
            $query
                ->andWhere('i.purchaseAmount IS NOT NULL')
                ->andWhere('i.purchaseCurrency = :currency')
                ->andWhere('i.purchaseAmount <= :maximumPrice')
                ->setParameter('currency', $maximumPrice->currency)
                ->setParameter('maximumPrice', $maximumPrice->amount);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    private function baseQuery(User $owner, CollectionItemSearchCriteria $criteria): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(CollectionItem::class, 'i')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->join('i.variant', 'v')
            ->where('i.owner = :owner')
            ->setParameter('owner', $owner);

        $this->applyUuidFilter($query, 'c.id', 'cardId', $criteria->cardId);
        $this->applyUuidFilter($query, 's.id', 'setId', $criteria->setId);

        if ($criteria->cardName !== null && trim($criteria->cardName) !== '') {
            $query
                ->andWhere('LOWER(c.name) LIKE :cardName')
                ->setParameter('cardName', '%'.mb_strtolower(trim($criteria->cardName)).'%');
        }

        if ($criteria->cardNumber !== null && trim($criteria->cardNumber) !== '') {
            $query
                ->andWhere('LOWER(c.number) LIKE :cardNumber')
                ->setParameter('cardNumber', '%'.mb_strtolower(trim($criteria->cardNumber)).'%');
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

        if ($criteria->status !== null) {
            $query->andWhere('i.status = :status')->setParameter('status', $criteria->status);
        }

        return $query;
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
