<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Exception\CatalogImportValidationFailed;
use App\Catalog\Application\Import\CatalogImportStagingStore;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCatalogImportStagingStore implements CatalogImportStagingStore
{
    private const int MAX_NORMALIZED_PAYLOAD_BYTES = 4_194_304;

    public function __construct(private Connection $connection)
    {
    }

    public function stageSet(string $runId, ImportedCardSet $set, int $position): void
    {
        $preparedPayload = $this->preparePayload($this->setPayload($set), 'set');
        $this->connection->insert('catalog_import_staged_sets', [
            'id' => Uuid::v7()->toRfc4122(),
            'import_run_id' => $runId,
            'position' => $position,
            'language' => $set->language,
            'external_id' => $set->externalId,
            'payload' => $preparedPayload['payload'],
            'record_checksum' => hash('sha256', $preparedPayload['encoded']),
            'created_at' => new DateTimeImmutable(),
        ], [
            'payload' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function stageCard(string $runId, ImportedCatalogCard $card, int $position): void
    {
        $preparedPayload = $this->preparePayload($this->cardPayload($card), 'card');
        $this->connection->insert('catalog_import_staged_cards', [
            'id' => Uuid::v7()->toRfc4122(),
            'import_run_id' => $runId,
            'position' => $position,
            'language' => $card->language,
            'set_external_id' => $card->setExternalId,
            'external_id' => $card->externalId,
            'payload' => $preparedPayload['payload'],
            'record_checksum' => hash('sha256', $preparedPayload['encoded']),
            'created_at' => new DateTimeImmutable(),
        ], [
            'payload' => Types::JSON,
            'created_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function sets(string $runId): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT payload
                FROM catalog_import_staged_sets
                WHERE import_run_id = :run_id
                ORDER BY position ASC, language ASC, external_id ASC
                SQL,
            ['run_id' => $runId],
        );

        foreach ($rows as $row) {
            yield $this->importedSet($this->decode($row['payload'] ?? null, 'set'));
        }
    }

    public function cards(string $runId, string $language, string $setExternalId): iterable
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT payload
                FROM catalog_import_staged_cards
                WHERE import_run_id = :run_id
                  AND language = :language
                  AND set_external_id = :set_external_id
                ORDER BY position ASC, external_id ASC
                SQL,
            [
                'run_id' => $runId,
                'language' => $language,
                'set_external_id' => $setExternalId,
            ],
        );

        foreach ($rows as $row) {
            yield $this->importedCard($this->decode($row['payload'] ?? null, 'card'));
        }
    }

    public function checksum(string $runId): string
    {
        $checksums = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT record_checksum
                FROM (
                    SELECT 0 AS record_type, position, language, external_id, record_checksum
                    FROM catalog_import_staged_sets
                    WHERE import_run_id = :run_id
                    UNION ALL
                    SELECT 1 AS record_type, position, language, external_id, record_checksum
                    FROM catalog_import_staged_cards
                    WHERE import_run_id = :run_id
                ) staged
                ORDER BY language ASC, record_type ASC, position ASC, external_id ASC
                SQL,
            ['run_id' => $runId],
        );
        $context = hash_init('sha256');
        foreach ($checksums as $checksum) {
            hash_update($context, (string) $checksum);
            hash_update($context, "\n");
        }

        return hash_final($context);
    }

    public function counts(string $runId): array
    {
        return [
            'sets' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM catalog_import_staged_sets WHERE import_run_id = :run_id',
                ['run_id' => $runId],
            ),
            'cards' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM catalog_import_staged_cards WHERE import_run_id = :run_id',
                ['run_id' => $runId],
            ),
        ];
    }

    public function clear(string $runId): void
    {
        $this->connection->delete('catalog_import_staged_cards', ['import_run_id' => $runId]);
        $this->connection->delete('catalog_import_staged_sets', ['import_run_id' => $runId]);
    }

    /** @return array<string, mixed> */
    private function setPayload(ImportedCardSet $set): array
    {
        return [
            'cardExternalIds' => $set->cardExternalIds,
            'externalId' => $set->externalId,
            'language' => $set->language,
            'logoUrl' => $set->logoUrl,
            'name' => $set->name,
            'printedTotal' => $set->printedTotal,
            'rawData' => $set->rawData,
            'releaseDate' => $set->releaseDate?->format(DATE_ATOM),
            'seriesExternalId' => $set->seriesExternalId,
            'seriesName' => $set->seriesName,
            'symbolUrl' => $set->symbolUrl,
            'total' => $set->total,
        ];
    }

    /** @return array<string, mixed> */
    private function cardPayload(ImportedCatalogCard $card): array
    {
        return [
            'cardType' => $card->cardType,
            'externalId' => $card->externalId,
            'illustrator' => $card->illustrator,
            'imageUrl' => $card->imageUrl,
            'isDetailed' => $card->isDetailed,
            'language' => $card->language,
            'metadata' => $card->metadata,
            'name' => $card->name,
            'number' => $card->number,
            'primaryCollectible' => $card->primaryCollectible,
            'rarity' => $card->rarity,
            'rawData' => $card->rawData,
            'releaseDate' => $card->releaseDate?->format(DATE_ATOM),
            'setExternalId' => $card->setExternalId,
            'thumbnailUrl' => $card->thumbnailUrl,
            'totalNumberInSet' => $card->totalNumberInSet,
            'variants' => array_map(
                static fn (VariantCode $variant): string => $variant->value,
                $card->variants,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{payload: array<string, mixed>, encoded: string}
     */
    private function preparePayload(array $payload, string $recordType): array
    {
        $canonicalPayload = $this->canonicalize($payload);
        if (!is_array($canonicalPayload) || array_is_list($canonicalPayload)) {
            $message = sprintf(
                'The normalized %s payload must be a JSON object.',
                $recordType,
            );
            throw new CatalogImportValidationFailed($message);
        }

        try {
            $encoded = json_encode(
                $canonicalPayload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            $message = sprintf('The normalized %s payload is not valid JSON.', $recordType);
            throw new CatalogImportValidationFailed($message, previous: $exception);
        }

        if (strlen($encoded) > self::MAX_NORMALIZED_PAYLOAD_BYTES) {
            $message = sprintf(
                'The normalized %s payload exceeds the 4 MiB import limit.',
                $recordType,
            );
            throw new CatalogImportValidationFailed($message);
        }

        /* @var array<string, mixed> $canonicalPayload */
        return ['payload' => $canonicalPayload, 'encoded' => $encoded];
    }

    /** @return array<string, mixed> */
    private function decode(mixed $payload, string $recordType): array
    {
        if (!is_string($payload)) {
            throw new CatalogImportValidationFailed(sprintf('The staged %s payload is missing.', $recordType));
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $message = sprintf('The staged %s payload is invalid.', $recordType);
            throw new CatalogImportValidationFailed($message, previous: $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new CatalogImportValidationFailed(sprintf('The staged %s payload is invalid.', $recordType));
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function importedSet(array $payload): ImportedCardSet
    {
        /** @var list<string> $cardExternalIds */
        $cardExternalIds = $payload['cardExternalIds'];
        /** @var array<string, mixed> $rawData */
        $rawData = $payload['rawData'];

        return new ImportedCardSet(
            language: (string) $payload['language'],
            externalId: (string) $payload['externalId'],
            name: (string) $payload['name'],
            seriesExternalId: (string) $payload['seriesExternalId'],
            seriesName: (string) $payload['seriesName'],
            printedTotal: (int) $payload['printedTotal'],
            total: (int) $payload['total'],
            releaseDate: $this->date($payload['releaseDate'] ?? null),
            logoUrl: $this->nullableString($payload['logoUrl'] ?? null),
            symbolUrl: $this->nullableString($payload['symbolUrl'] ?? null),
            cardExternalIds: $cardExternalIds,
            rawData: $rawData,
        );
    }

    /** @param array<string, mixed> $payload */
    private function importedCard(array $payload): ImportedCatalogCard
    {
        /** @var list<string> $variantValues */
        $variantValues = $payload['variants'];
        /** @var array<string, mixed> $metadata */
        $metadata = $payload['metadata'];
        /** @var array<string, mixed> $rawData */
        $rawData = $payload['rawData'];

        return new ImportedCatalogCard(
            language: (string) $payload['language'],
            externalId: (string) $payload['externalId'],
            setExternalId: (string) $payload['setExternalId'],
            name: (string) $payload['name'],
            number: (string) $payload['number'],
            totalNumberInSet: $this->nullableString($payload['totalNumberInSet'] ?? null),
            rarity: (string) $payload['rarity'],
            cardType: (string) $payload['cardType'],
            primaryCollectible: $this->nullableString($payload['primaryCollectible'] ?? null),
            illustrator: $this->nullableString($payload['illustrator'] ?? null),
            imageUrl: $this->nullableString($payload['imageUrl'] ?? null),
            thumbnailUrl: $this->nullableString($payload['thumbnailUrl'] ?? null),
            releaseDate: $this->date($payload['releaseDate'] ?? null),
            variants: array_map(
                static fn (string $variant): VariantCode => VariantCode::from($variant),
                $variantValues,
            ),
            metadata: $metadata,
            rawData: $rawData,
            isDetailed: ($payload['isDetailed'] ?? false) === true,
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_scalar($value) || $value === null) {
                return $value;
            }

            throw new CatalogImportValidationFailed('A normalized catalog payload contains a non-JSON value.');
        }

        $canonical = array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        if (!array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
