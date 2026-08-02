<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence;

use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use Doctrine\DBAL\Connection;
use UnexpectedValueException;

final readonly class DoctrineTrackedPriceCardRepository implements TrackedPriceCardRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function cardIds(): iterable
    {
        $sql = <<<'SQL'
            SELECT tracked.catalog_card_id::text AS card_id
            FROM (
                SELECT wishlist_item.catalog_card_id
                FROM wishlist_items wishlist_item
                WHERE wishlist_item.completed_at IS NULL
                UNION
                SELECT collection_item.catalog_card_id
                FROM collection_items collection_item
                WHERE collection_item.status <> 'archived'
                UNION
                SELECT collection_item.catalog_card_id
                FROM listings listing
                INNER JOIN collection_items collection_item ON collection_item.id = listing.collection_item_id
                WHERE listing.status IN ('draft', 'pending_review', 'active', 'reserved')
            ) tracked
            ORDER BY tracked.catalog_card_id
            SQL;

        foreach ($this->connection->executeQuery($sql)->iterateAssociative() as $row) {
            $cardId = $row['card_id'] ?? null;
            if (!is_string($cardId)) {
                throw new UnexpectedValueException('A tracked pricing candidate did not contain a card UUID.');
            }

            yield $cardId;
        }
    }
}
