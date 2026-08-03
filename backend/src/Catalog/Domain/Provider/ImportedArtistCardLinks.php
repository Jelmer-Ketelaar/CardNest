<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Provider;

use InvalidArgumentException;

/**
 * Provider-neutral artist attribution backed by external card identifiers.
 */
final readonly class ImportedArtistCardLinks
{
    /** @param list<string> $cardExternalIds */
    public function __construct(
        public string $language,
        public string $canonicalName,
        public string $sourceReference,
        public array $cardExternalIds,
    ) {
        if (trim($language) === '') {
            throw new InvalidArgumentException('An imported artist language is required.');
        }
        if (trim($canonicalName) === '') {
            throw new InvalidArgumentException('An imported artist name is required.');
        }
        if (trim($sourceReference) === '') {
            throw new InvalidArgumentException('An imported artist source reference is required.');
        }
        if (mb_strlen($language) > 10 || mb_strlen($canonicalName) > 180 || mb_strlen($sourceReference) > 512) {
            throw new InvalidArgumentException('Imported artist identity fields exceed their supported length.');
        }
        foreach ($cardExternalIds as $cardExternalId) {
            if (trim($cardExternalId) === '' || mb_strlen($cardExternalId) > 180) {
                throw new InvalidArgumentException('Imported artist card references must be non-empty and at most 180 characters.');
            }
        }
    }
}
