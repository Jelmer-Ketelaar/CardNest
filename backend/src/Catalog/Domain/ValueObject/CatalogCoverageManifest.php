<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Entity\CatalogCoverageDimension;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CatalogCoverageManifest
{
    /**
     * @param list<CatalogCoverageManifestSet> $sets
     * @param list<CatalogCoverageDimension>   $blockedDimensions
     */
    public function __construct(
        public string $provider,
        public string $region,
        public string $language,
        public string $evidenceSource,
        public string $evidenceVersion,
        public string $evidenceReference,
        public DateTimeImmutable $dataCutoff,
        public array $sets,
        public ?int $expectedPokemonSpeciesCount = null,
        public ?int $expectedPokemonFormCount = null,
        public ?int $expectedImageCardCount = null,
        public ?int $expectedArtistCardCount = null,
        public ?int $expectedPokemonMappedCardCount = null,
        public ?int $expectedPricedCardCount = null,
        public array $blockedDimensions = [],
    ) {
        $this->assertIdentifier($provider, 100, 'provider');
        $this->assertIdentifier($region, 50, 'region');
        $this->assertIdentifier($language, 10, 'language');
        $this->assertIdentifier($evidenceSource, 180, 'evidence source');
        $this->assertIdentifier($evidenceVersion, 180, 'evidence version');
        $this->assertIdentifier($evidenceReference, 1_024, 'evidence reference');
        if ($sets === []) {
            throw new InvalidArgumentException('A coverage manifest requires at least one expected set.');
        }

        $setIds = array_map(
            static fn (CatalogCoverageManifestSet $set): string => $set->externalId,
            $sets,
        );
        if (count(array_unique($setIds)) !== count($setIds)) {
            throw new InvalidArgumentException('Coverage manifest set IDs must be unique.');
        }

        foreach ($this->optionalExpectations() as $expectation) {
            if ($expectation !== null && $expectation < 0) {
                throw new InvalidArgumentException('Coverage expectations cannot be negative.');
            }
        }
        $blockedValues = array_map(
            static fn (CatalogCoverageDimension $dimension): string => $dimension->value,
            $blockedDimensions,
        );
        if (count(array_unique($blockedValues)) !== count($blockedValues)) {
            throw new InvalidArgumentException('Blocked coverage dimensions must be unique.');
        }
    }

    public function expectedSetCount(): int
    {
        return count($this->sets);
    }

    public function expectedCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogCoverageManifestSet $set): int => $set->expectedCardCount,
            $this->sets,
        ));
    }

    /** @return list<string> */
    public function catalogCodes(): array
    {
        return array_map(
            fn (CatalogCoverageManifestSet $set): string => $set->catalogCode($this->provider, $this->language),
            $this->sets,
        );
    }

    public function isBlocked(CatalogCoverageDimension $dimension): bool
    {
        return in_array($dimension, $this->blockedDimensions, true);
    }

    public function checksum(): string
    {
        $sortedSets = $this->sets;
        usort(
            $sortedSets,
            static fn (CatalogCoverageManifestSet $left, CatalogCoverageManifestSet $right): int => $left->externalId <=> $right->externalId,
        );
        $sets = array_map(
            static fn (CatalogCoverageManifestSet $set): array => [
                'externalId' => $set->externalId,
                'expectedCardCount' => $set->expectedCardCount,
            ],
            $sortedSets,
        );
        $blocked = array_map(
            static fn (CatalogCoverageDimension $dimension): string => $dimension->value,
            $this->blockedDimensions,
        );
        sort($blocked, SORT_STRING);

        return hash('sha256', json_encode([
            'provider' => $this->provider,
            'region' => $this->region,
            'language' => $this->language,
            'evidenceSource' => $this->evidenceSource,
            'evidenceVersion' => $this->evidenceVersion,
            'evidenceReference' => $this->evidenceReference,
            'dataCutoff' => $this->dataCutoff->format(DATE_ATOM),
            'sets' => $sets,
            'expectations' => $this->optionalExpectations(),
            'blockedDimensions' => $blocked,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{
     *     pokemonSpecies: int|null,
     *     pokemonForms: int|null,
     *     images: int|null,
     *     artists: int|null,
     *     pokemonMapping: int|null,
     *     prices: int|null
     * }
     */
    private function optionalExpectations(): array
    {
        return [
            'pokemonSpecies' => $this->expectedPokemonSpeciesCount,
            'pokemonForms' => $this->expectedPokemonFormCount,
            'images' => $this->expectedImageCardCount,
            'artists' => $this->expectedArtistCardCount,
            'pokemonMapping' => $this->expectedPokemonMappedCardCount,
            'prices' => $this->expectedPricedCardCount,
        ];
    }

    private function assertIdentifier(string $value, int $maxLength, string $field): void
    {
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            throw new InvalidArgumentException(sprintf('The coverage %s is invalid.', $field));
        }
    }
}
