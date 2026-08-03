<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use InvalidArgumentException;

final readonly class ImportCatalogArtistsRequest
{
    /** @var non-empty-list<string> */
    public array $languages;

    /** @var list<string> */
    public array $onlyArtistNames;

    /** @var int<1, 500> */
    public int $batchSize;

    /**
     * @param list<string> $languages
     * @param list<string> $onlyArtistNames
     */
    public function __construct(
        array $languages,
        array $onlyArtistNames = [],
        int $batchSize = 100,
    ) {
        if ($languages === []) {
            throw new InvalidArgumentException('At least one catalog language is required.');
        }
        if ($batchSize < 1 || $batchSize > 500) {
            throw new InvalidArgumentException('The artist import batch size must be between 1 and 500.');
        }

        $this->languages = $languages;
        $this->onlyArtistNames = $onlyArtistNames;
        $this->batchSize = $batchSize;
    }
}
