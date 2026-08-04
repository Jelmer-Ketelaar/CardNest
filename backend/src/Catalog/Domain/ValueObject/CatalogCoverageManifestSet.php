<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;

final readonly class CatalogCoverageManifestSet
{
    public function __construct(
        public string $externalId,
        public int $expectedCardCount,
    ) {
        if (
            $externalId !== trim($externalId)
            || $externalId === ''
            || mb_strlen($externalId) > 180
            || preg_match('/[\x00-\x1F\x7F]/u', $externalId) === 1
        ) {
            throw new InvalidArgumentException('A coverage manifest set ID must contain 1 to 180 characters.');
        }
        if ($expectedCardCount < 0) {
            throw new InvalidArgumentException('A coverage manifest card count cannot be negative.');
        }
    }

    public function catalogCode(string $provider, string $language): string
    {
        return sprintf('%s:%s:%s', $provider, $language, $this->externalId);
    }
}
