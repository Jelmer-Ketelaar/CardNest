<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Coverage;

use App\Catalog\Application\Coverage\CatalogCoverageDataSource;
use App\Catalog\Application\Coverage\CatalogCoverageMeasurement;
use App\Catalog\Application\Coverage\CatalogSetCoverageMeasurement;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DoctrineCatalogCoverageDataSource implements CatalogCoverageDataSource
{
    public function __construct(private Connection $connection)
    {
    }

    public function measure(CatalogCoverageManifest $manifest): CatalogCoverageMeasurement
    {
        $catalogCodes = $manifest->catalogCodes();
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT card_set.code,
                       COUNT(card.id) AS card_count,
                       COUNT(card.id) FILTER (
                           WHERE card.number_sort_key IS NOT NULL AND card.number_sort_key <> ''
                       ) AS ordered_card_count,
                       COUNT(card.id) FILTER (
                           WHERE card.image_url IS NOT NULL AND card.image_url <> ''
                       ) AS image_card_count,
                       COUNT(card.id) FILTER (
                           WHERE EXISTS (
                               SELECT 1
                               FROM catalog_card_artists artist_link
                               WHERE artist_link.catalog_card_id = card.id
                           )
                       ) AS artist_card_count,
                       COUNT(card.id) FILTER (
                           WHERE EXISTS (
                               SELECT 1
                               FROM price_snapshots price
                               WHERE price.catalog_card_id = card.id
                                 AND price.variant <> 'unavailable'
                           )
                       ) AS priced_card_count
                FROM card_sets card_set
                LEFT JOIN catalog_cards card ON card.set_id = card_set.id
                WHERE card_set.code IN (:catalog_codes)
                GROUP BY card_set.code
                ORDER BY card_set.code ASC
                SQL,
            ['catalog_codes' => $catalogCodes],
            ['catalog_codes' => ArrayParameterType::STRING],
        );

        $sets = array_map(
            static fn (array $row): CatalogSetCoverageMeasurement => new CatalogSetCoverageMeasurement(
                catalogCode: (string) $row['code'],
                cardCount: (int) $row['card_count'],
                stableOrderCardCount: (int) $row['ordered_card_count'],
                officialOrderCardCount: null,
                imageCardCount: (int) $row['image_card_count'],
                artistCardCount: (int) $row['artist_card_count'],
                pricedCardCount: (int) $row['priced_card_count'],
            ),
            $rows,
        );

        return new CatalogCoverageMeasurement(
            sets: $sets,
            duplicateSetCount: $this->duplicateSetCount($catalogCodes),
            duplicateCardCount: $this->duplicateCardCount($catalogCodes),
            orphanCardCount: $this->orphanCardCount(),
        );
    }

    /** @param list<string> $catalogCodes */
    private function duplicateSetCount(array $catalogCodes): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM (
                    SELECT card_set.code
                    FROM card_sets card_set
                    WHERE card_set.code IN (:catalog_codes)
                    GROUP BY card_set.code
                    HAVING COUNT(*) > 1
                ) duplicate_set
                SQL,
            ['catalog_codes' => $catalogCodes],
            ['catalog_codes' => ArrayParameterType::STRING],
        );
    }

    /** @param list<string> $catalogCodes */
    private function duplicateCardCount(array $catalogCodes): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM (
                    SELECT card.set_id, card.number
                    FROM catalog_cards card
                    INNER JOIN card_sets card_set ON card_set.id = card.set_id
                    WHERE card_set.code IN (:catalog_codes)
                    GROUP BY card.set_id, card.number
                    HAVING COUNT(*) > 1
                ) duplicate_card
                SQL,
            ['catalog_codes' => $catalogCodes],
            ['catalog_codes' => ArrayParameterType::STRING],
        );
    }

    private function orphanCardCount(): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM catalog_cards card
                LEFT JOIN card_sets card_set ON card_set.id = card.set_id
                WHERE card_set.id IS NULL
                SQL,
        );
    }
}
