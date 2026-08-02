<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Provider;

use App\Catalog\Domain\Entity\CatalogLanguage;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * TCGdex v2 adapter. It only stores source data and remote asset URLs; images
 * are never downloaded or cached by this provider.
 */
final readonly class TcgDexCardCatalogProvider implements CardCatalogProvider
{
    private const string DEFAULT_BASE_URL = 'https://api.tcgdex.net/v2';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    public function importSets(string $language, array $onlySetIds = []): iterable
    {
        $this->assertSupportedLanguage($language);
        $setIds = $onlySetIds;

        if ($setIds === []) {
            $summaries = $this->requestList(sprintf('%s/sets', rawurlencode($language)));
            $setIds = [];
            foreach ($summaries as $summary) {
                if (!is_array($summary)) {
                    throw new CatalogProviderException('TCGdex returned an invalid set summary.');
                }

                $setIds[] = $this->requiredString($summary, 'id', 'set summary');
            }
        }

        foreach (array_values(array_unique($setIds)) as $setId) {
            yield $this->importSet($language, $setId);
        }
    }

    public function importCards(ImportedCardSet $set, bool $includeCardDetails = false): iterable
    {
        if (!$includeCardDetails) {
            $cards = $this->requiredList($set->rawData, 'cards', 'set');
            foreach ($cards as $cardSummary) {
                if (!is_array($cardSummary) || array_is_list($cardSummary)) {
                    throw new CatalogProviderException('TCGdex returned an invalid card summary in a set.');
                }

                /* @var array<string, mixed> $cardSummary */
                yield $this->toImportedCardSummary($set, $cardSummary);
            }

            return;
        }

        foreach ($set->cardExternalIds as $cardId) {
            $card = $this->findCard($set->language, $cardId);
            if (!$card instanceof ImportedCatalogCard) {
                throw new CatalogProviderException(sprintf('TCGdex card "%s" was not found.', $cardId));
            }

            yield $card;
        }
    }

    public function findCard(string $language, string $externalId): ?ImportedCatalogCard
    {
        $this->assertSupportedLanguage($language);
        $data = $this->requestObject(
            sprintf('%s/cards/%s', rawurlencode($language), rawurlencode($externalId)),
            allowNotFound: true,
        );
        if ($data === null) {
            return null;
        }

        return $this->toImportedCard($language, $data);
    }

    /** @param array<string, mixed> $summary */
    private function toImportedCardSummary(ImportedCardSet $set, array $summary): ImportedCatalogCard
    {
        $totalNumberInSet = (string) $set->printedTotal;
        $image = $this->optionalString($summary, 'image');

        return new ImportedCatalogCard(
            language: $set->language,
            externalId: $this->requiredString($summary, 'id', 'card summary'),
            setExternalId: $set->externalId,
            name: $this->requiredString($summary, 'name', 'card summary'),
            number: $this->displayNumber(
                $this->requiredString($summary, 'localId', 'card summary'),
                $totalNumberInSet,
            ),
            totalNumberInSet: $totalNumberInSet,
            rarity: 'Unknown',
            cardType: 'Unknown',
            primaryCollectible: null,
            illustrator: null,
            imageUrl: $this->cardImageUrl($image, 'high'),
            thumbnailUrl: $this->cardImageUrl($image, 'low'),
            releaseDate: $set->releaseDate,
            variants: [VariantCode::Normal],
            metadata: [
                'source' => 'tcgdex',
                'sourceDetailLevel' => 'summary',
                'imagePolicy' => 'external_url_only',
            ],
            rawData: $summary,
        );
    }

    private function importSet(string $language, string $externalId): ImportedCardSet
    {
        $data = $this->requestObject(sprintf('%s/sets/%s', rawurlencode($language), rawurlencode($externalId)));
        if ($data === null) {
            throw new CatalogProviderException(sprintf('TCGdex set "%s" was not found.', $externalId));
        }

        $cards = $this->requiredList($data, 'cards', 'set');
        $cardIds = [];
        foreach ($cards as $card) {
            if (!is_array($card)) {
                throw new CatalogProviderException('TCGdex returned an invalid card summary in a set.');
            }

            $cardIds[] = $this->requiredString($card, 'id', 'card summary');
        }

        $cardCount = $this->optionalObject($data, 'cardCount') ?? [];
        $total = $this->optionalInteger($cardCount, 'total') ?? count($cardIds);
        $printedTotal = $this->optionalInteger($cardCount, 'official') ?? $total;
        $series = $this->optionalObject($data, 'serie') ?? ['id' => 'misc', 'name' => 'Miscellaneous'];

        return new ImportedCardSet(
            language: $language,
            externalId: $this->requiredString($data, 'id', 'set'),
            name: $this->requiredString($data, 'name', 'set'),
            seriesExternalId: $this->requiredString($series, 'id', 'series'),
            seriesName: $this->requiredString($series, 'name', 'series'),
            printedTotal: $printedTotal,
            total: $total,
            releaseDate: $this->dateOrNull($data['releaseDate'] ?? null),
            logoUrl: $this->setAssetUrl($this->optionalString($data, 'logo')),
            symbolUrl: $this->setAssetUrl($this->optionalString($data, 'symbol')),
            cardExternalIds: $cardIds,
            rawData: $data,
        );
    }

    /** @param array<string, mixed> $data */
    private function toImportedCard(string $language, array $data): ImportedCatalogCard
    {
        $set = $this->requiredObject($data, 'set', 'card');
        $localId = $this->requiredString($data, 'localId', 'card');
        $cardCount = $this->optionalObject($set, 'cardCount') ?? [];
        $totalNumber = $this->optionalInteger($cardCount, 'official')
            ?? $this->optionalInteger($cardCount, 'total');
        $totalNumberInSet = $totalNumber !== null ? (string) $totalNumber : null;
        $image = $this->optionalString($data, 'image');

        return new ImportedCatalogCard(
            language: $language,
            externalId: $this->requiredString($data, 'id', 'card'),
            setExternalId: $this->requiredString($set, 'id', 'card set'),
            name: $this->requiredString($data, 'name', 'card'),
            number: $this->displayNumber($localId, $totalNumberInSet),
            totalNumberInSet: $totalNumberInSet,
            rarity: $this->optionalString($data, 'rarity') ?? 'Unknown',
            cardType: $this->optionalString($data, 'category') ?? 'Unknown',
            primaryCollectible: $this->optionalString($data, 'category') === 'Pokemon'
                ? $this->requiredString($data, 'name', 'card')
                : null,
            illustrator: $this->optionalString($data, 'illustrator'),
            imageUrl: $this->cardImageUrl($image, 'high'),
            thumbnailUrl: $this->cardImageUrl($image, 'low'),
            releaseDate: null,
            variants: $this->variants($data),
            metadata: $this->metadata($data),
            rawData: $data,
            isDetailed: true,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<VariantCode>
     */
    private function variants(array $data): array
    {
        /** @var array<string, VariantCode> $variants */
        $variants = [];
        $flags = $this->optionalObject($data, 'variants') ?? [];
        $flagMappings = [
            'normal' => VariantCode::Normal,
            'holo' => VariantCode::Holo,
            'reverse' => VariantCode::ReverseHolo,
            'firstEdition' => VariantCode::FirstEdition,
            'wPromo' => VariantCode::Promo,
        ];
        foreach ($flagMappings as $field => $variant) {
            if (($flags[$field] ?? false) === true) {
                $variants[$variant->value] = $variant;
            }
        }

        $detailedVariants = $data['variants_detailed'] ?? [];
        if (!is_array($detailedVariants)) {
            throw new CatalogProviderException('TCGdex returned invalid detailed variants.');
        }

        foreach ($detailedVariants as $detailedVariant) {
            if (!is_array($detailedVariant)) {
                throw new CatalogProviderException('TCGdex returned an invalid detailed variant.');
            }

            $this->addMappedVariant($variants, $detailedVariant['type'] ?? null, [
                'normal' => VariantCode::Normal,
                'holo' => VariantCode::Holo,
                'reverse' => VariantCode::ReverseHolo,
                'cosmos-holo' => VariantCode::CosmosHolo,
                'promo' => VariantCode::Promo,
            ]);
            $this->addMappedVariant($variants, $detailedVariant['subtype'] ?? null, [
                'unlimited' => VariantCode::Unlimited,
                'shadowless' => VariantCode::Shadowless,
            ]);

            $stamps = $detailedVariant['stamp'] ?? [];
            if (!is_array($stamps)) {
                throw new CatalogProviderException('TCGdex returned invalid variant stamps.');
            }

            foreach ($stamps as $stamp) {
                $this->addMappedVariant($variants, $stamp, [
                    '1st-edition' => VariantCode::FirstEdition,
                    'promo' => VariantCode::Promo,
                ]);
            }
        }

        if ($variants === []) {
            $variants[VariantCode::Normal->value] = VariantCode::Normal;
        }

        /** @var list<VariantCode> $resolvedVariants */
        $resolvedVariants = array_values($variants);

        return $resolvedVariants;
    }

    /**
     * @param array<string, VariantCode> $variants
     * @param array<string, VariantCode> $mappings
     */
    private function addMappedVariant(array &$variants, mixed $value, array $mappings): void
    {
        if (!is_string($value) || !isset($mappings[$value])) {
            return;
        }

        $variant = $mappings[$value];
        $variants[$variant->value] = $variant;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function metadata(array $data): array
    {
        $metadata = [
            'source' => 'tcgdex',
            'sourceDetailLevel' => 'detail',
            'imagePolicy' => 'external_url_only',
        ];
        $allowedFields = [
            'dexId',
            'hp',
            'types',
            'evolveFrom',
            'description',
            'stage',
            'abilities',
            'attacks',
            'weaknesses',
            'resistances',
            'retreat',
            'legal',
            'updated',
            'variants_detailed',
        ];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $metadata[$field] = $data[$field];
            }
        }

        return $metadata;
    }

    private function displayNumber(string $localId, ?string $total): string
    {
        if ($total === null || $total === '' || str_contains($localId, '/')) {
            return $localId;
        }

        return $localId.'/'.$total;
    }

    /** @return list<mixed> */
    private function requestList(string $path): array
    {
        $data = $this->requestJson($path);
        if ($data === null || !array_is_list($data)) {
            throw new CatalogProviderException('TCGdex returned an object where a list was expected.');
        }

        /* @var list<mixed> $data */
        return $data;
    }

    /** @return array<string, mixed>|null */
    private function requestObject(string $path, bool $allowNotFound = false): ?array
    {
        $data = $this->requestJson($path, $allowNotFound);
        if ($data === null) {
            return null;
        }
        if (array_is_list($data)) {
            throw new CatalogProviderException('TCGdex returned a list where an object was expected.');
        }

        /* @var array<string, mixed> $data */
        return $data;
    }

    /** @return array<mixed>|null */
    private function requestJson(string $path, bool $allowNotFound = false): ?array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 30.0,
            ]);
            $status = $response->getStatusCode();
            if ($status === 404 && $allowNotFound) {
                return null;
            }
            if ($status < 200 || $status >= 300) {
                throw new CatalogProviderException(sprintf('TCGdex responded with HTTP status %d.', $status));
            }

            $data = $response->toArray(false);
        } catch (CatalogProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CatalogProviderException('TCGdex could not be reached or returned invalid JSON.', previous: $exception);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field, string $context): string
    {
        $value = $this->optionalString($data, $field);
        if ($value === null || $value === '') {
            throw new CatalogProviderException(sprintf('TCGdex %s is missing "%s".', $context, $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<mixed>
     */
    private function requiredList(array $data, string $field, string $context): array
    {
        $value = $data[$field] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new CatalogProviderException(sprintf('TCGdex %s is missing "%s".', $context, $field));
        }

        /* @var list<mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function requiredObject(array $data, string $field, string $context): array
    {
        $value = $data[$field] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            throw new CatalogProviderException(sprintf('TCGdex %s is missing "%s".', $context, $field));
        }

        /* @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private function optionalObject(array $data, string $field): ?array
    {
        $value = $data[$field] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        /* @var array<string, mixed> $value */
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalInteger(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        return is_int($value) ? $value : null;
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function cardImageUrl(?string $baseUrl, string $quality): ?string
    {
        if ($baseUrl === null) {
            return null;
        }

        $baseUrl = preg_replace('~/(?:high|low)\.(?:jpg|png|webp)$~', '', rtrim($baseUrl, '/'));
        if (!is_string($baseUrl)) {
            throw new CatalogProviderException('TCGdex returned an invalid card image URL.');
        }

        return sprintf('%s/%s.webp', $baseUrl, $quality);
    }

    private function setAssetUrl(?string $baseUrl): ?string
    {
        if ($baseUrl === null || preg_match('/\.(?:jpg|png|webp)$/', $baseUrl) === 1) {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'.webp';
    }

    private function assertSupportedLanguage(string $language): void
    {
        if (CatalogLanguage::tryFrom($language) === null) {
            throw new CatalogProviderException(sprintf('Unsupported TCGdex language "%s".', $language));
        }
    }
}
