<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ImportCatalogRequest
{
    /**
     * @param list<string> $languages
     * @param list<string> $onlySetIds
     */
    public function __construct(
        public array $languages,
        public array $onlySetIds = [],
        public int $batchSize = 100,
        public bool $includeCardDetails = false,
        public ?string $sourceVersion = null,
        public ?DateTimeImmutable $dataCutoff = null,
    ) {
        if ($languages === []) {
            throw new InvalidArgumentException('At least one catalog import language is required.');
        }
        if ($batchSize < 1 || $batchSize > 1_000) {
            throw new InvalidArgumentException('The catalog import batch size must be between 1 and 1000.');
        }
        foreach ($languages as $language) {
            $this->assertIdentifier($language, 10, 'language');
        }
        foreach ($onlySetIds as $setId) {
            $this->assertIdentifier($setId, 180, 'set identifier');
        }
        if (count(array_unique($languages)) !== count($languages)
            || count(array_unique($onlySetIds)) !== count($onlySetIds)) {
            throw new InvalidArgumentException('Catalog import language and set identifiers must be unique.');
        }
        if ($sourceVersion !== null && (
            $sourceVersion !== trim($sourceVersion)
            || $sourceVersion === ''
            || mb_strlen($sourceVersion) > 180
            || preg_match('/[\x00-\x1F\x7F]/u', $sourceVersion) === 1
        )) {
            $message = 'The catalog source version must contain 1 to 180 trimmed characters without controls.';
            throw new InvalidArgumentException($message);
        }
    }

    /** @return array{languages: list<string>, setIds: list<string>, includeCardDetails: bool} */
    public function dataScope(): array
    {
        $languages = $this->languages;
        $setIds = $this->onlySetIds;
        sort($languages, SORT_STRING);
        sort($setIds, SORT_STRING);

        return [
            'languages' => $languages,
            'setIds' => $setIds,
            'includeCardDetails' => $this->includeCardDetails,
        ];
    }

    public function scopeKey(string $provider): string
    {
        return hash('sha256', json_encode(
            ['provider' => $provider, 'scope' => $this->dataScope()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    public function lockName(string $provider): string
    {
        return sprintf('catalog-import-%s', $this->scopeKey($provider));
    }

    private function assertIdentifier(string $value, int $maxLength, string $name): void
    {
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            $message = sprintf(
                'Catalog import %s must contain 1 to %d trimmed characters.',
                $name,
                $maxLength,
            );
            throw new InvalidArgumentException($message);
        }
    }
}
