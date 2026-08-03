<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Provider;

/**
 * Optional catalog-provider capability for importing source-backed artist to
 * card mappings without fetching every complete card payload individually.
 */
interface CatalogArtistProvider
{
    public function getProviderName(): string;

    /**
     * @param list<string> $onlyArtistNames
     *
     * @return iterable<ImportedArtistCardLinks>
     */
    public function importArtistCardLinks(string $language, array $onlyArtistNames = []): iterable;
}
