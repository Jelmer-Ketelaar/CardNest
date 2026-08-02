<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use InvalidArgumentException;

final readonly class ImportCatalogRequest
{
    /**
     * @param non-empty-list<string> $languages
     * @param list<string>           $onlySetIds
     */
    public function __construct(
        public array $languages,
        public array $onlySetIds = [],
        public int $batchSize = 100,
        public bool $includeCardDetails = false,
    ) {
        if ($batchSize < 1 || $batchSize > 1_000) {
            throw new InvalidArgumentException('The catalog import batch size must be between 1 and 1000.');
        }
    }
}
