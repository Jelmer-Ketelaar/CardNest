<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Provider;

/**
 * Port for catalog sources. Source-specific HTTP and payload concerns belong
 * in Infrastructure implementations; the application layer only consumes
 * normalized imported records.
 */
interface CardCatalogProvider
{
    /**
     * @param list<string> $onlySetIds
     *
     * @return iterable<ImportedCardSet>
     */
    public function importSets(string $language, array $onlySetIds = []): iterable;

    /** @return iterable<ImportedCatalogCard> */
    public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable;

    public function findCard(string $language, string $externalId): ?ImportedCatalogCard;
}
