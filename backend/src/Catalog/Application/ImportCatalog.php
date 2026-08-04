<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogImportResult;
use App\Catalog\Application\Dto\ImportCatalogRequest;
use App\Catalog\Application\Exception\CatalogImportAlreadyRunning;
use App\Catalog\Application\Exception\CatalogImportValidationFailed;
use App\Catalog\Application\Import\CatalogImportStagingStore;
use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Application\Import\CatalogImportValidator;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Entity\CatalogImportStatus;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

/**
 * Stages and validates a complete requested provider scope before publishing
 * live catalog records in bounded, per-set transactions.
 */
final readonly class ImportCatalog
{
    private const float LOCK_TTL_SECONDS = 3_600.0;

    public function __construct(
        private CardCatalogProvider $provider,
        private CatalogImportStore $store,
        private CatalogImportStagingStore $staging,
        private CatalogImportRunRepository $runs,
        private CatalogImportValidator $validator,
        private CatalogDiscrepancyRecorder $discrepancyRecorder,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * @param callable(string, string, int): void|null $reportSet
     */
    public function execute(ImportCatalogRequest $request, ?callable $reportSet = null): CatalogImportResult
    {
        $provider = $this->provider->getProviderName();
        $run = new CatalogImportRun(
            provider: $provider,
            scopeKey: $request->scopeKey($provider),
            dataScope: $request->dataScope(),
            sourceVersion: $request->sourceVersion,
            dataCutoff: $request->dataCutoff,
        );
        $this->runs->save($run);
        $lock = $this->lockFactory->createLock($request->lockName($provider), self::LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            $run->fail('Another catalog import is already running for the same provider scope.');
            $this->runs->save($run);

            throw new CatalogImportAlreadyRunning($run->getFailureReason() ?? 'Catalog import scope is locked.');
        }

        try {
            [$setCount, $cardCount] = $this->stage($request, $run, $lock);
            $run->markValidating($this->staging->checksum($run->getId()->toRfc4122()), $setCount, $cardCount);
            $this->runs->save($run);

            $duplicate = $this->completedDuplicate($run);
            if ($duplicate instanceof CatalogImportRun) {
                $run->skipAsDuplicate($duplicate);
                $this->runs->save($run);
                $result = new CatalogImportResult();
                $result->attachRun($run);

                return $result;
            }

            $run->markPublishing();
            $this->runs->save($run);
            $this->store->clear();
            $result = $this->publish($request, $run, $lock, $reportSet);

            $this->store->clear();
            $completedRun = $this->runs->get($run->getId()->toRfc4122())
                ?? throw new RuntimeException('The catalog import run could not be reloaded.');
            $completedRun->complete(
                $result->getCreatedSets() + $result->getExistingSets(),
                $result->getCreatedCards() + $result->getSyncedCards(),
            );
            $this->runs->save($completedRun);
            $result->attachRun($completedRun);

            return $result;
        } catch (Throwable $exception) {
            $this->recordFailure($run->getId()->toRfc4122(), $exception);

            throw $exception;
        } finally {
            $this->staging->clear($run->getId()->toRfc4122());
            $lock->release();
        }
    }

    /** @return array{int, int} */
    private function stage(ImportCatalogRequest $request, CatalogImportRun $run, LockInterface $lock): array
    {
        $provider = $run->getProvider();
        $runId = $run->getId()->toRfc4122();
        $setPosition = 0;
        $cardCount = 0;
        $seenSets = [];
        $seenCards = [];

        foreach ($request->languages as $language) {
            $returnedSetIds = [];
            foreach ($this->provider->importSets($language, $request->onlySetIds) as $set) {
                if ($set->language !== $language) {
                    $message = sprintf(
                        'The provider returned language "%s" while staging "%s".',
                        $set->language,
                        $language,
                    );
                    throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: sprintf('language-mismatch:%s:%s', $language, $set->language), entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::ProviderError, severity: CatalogDiscrepancySeverity::High, message: $message, externalReference: $set->externalId, language: $language));
                }

                $setKey = sprintf('%s:%s', $set->language, $set->externalId);
                if (isset($seenSets[$setKey])) {
                    throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'duplicate-set:'.$setKey, entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::DuplicateSet, severity: CatalogDiscrepancySeverity::Critical, message: sprintf('Duplicate staged set "%s".', $setKey), externalReference: $set->externalId, language: $set->language));
                }
                $this->validator->validateSet($provider, $set);
                $seenSets[$setKey] = true;
                $returnedSetIds[$set->externalId] = true;
                $this->staging->stageSet($runId, $set, $setPosition);

                $expectedCardIds = array_fill_keys($set->cardExternalIds, true);
                $stagedSetCardIds = [];
                $cardPosition = 0;
                foreach ($this->provider->importCards($set, $request->includeCardDetails) as $card) {
                    $this->validator->validateCard($provider, $set, $card, $expectedCardIds);
                    $cardKey = sprintf('%s:%s', $card->language, $card->externalId);
                    if (isset($seenCards[$cardKey])) {
                        throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'duplicate-card:'.$cardKey, entityType: CatalogDiscrepancyEntityType::Card, type: CatalogDiscrepancyType::DuplicateCard, severity: CatalogDiscrepancySeverity::Critical, message: sprintf('Duplicate staged card "%s".', $cardKey), externalReference: $card->externalId, language: $card->language));
                    }

                    $seenCards[$cardKey] = true;
                    $stagedSetCardIds[$card->externalId] = true;
                    $this->staging->stageCard($runId, $card, $cardPosition);
                    ++$cardPosition;
                    ++$cardCount;
                    if ($cardCount % 50 === 0) {
                        $lock->refresh(self::LOCK_TTL_SECONDS);
                    }
                }

                if (count($stagedSetCardIds) !== count($expectedCardIds)) {
                    $missingCardIds = array_keys(array_diff_key($expectedCardIds, $stagedSetCardIds));
                    $message = sprintf(
                        'Set "%s" is missing %d staged cards; first missing reference: "%s".',
                        $setKey,
                        count($missingCardIds),
                        $missingCardIds[0] ?? 'unknown',
                    );
                    throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'missing-cards:'.$setKey, entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::MissingCard, severity: CatalogDiscrepancySeverity::Critical, message: $message, externalReference: $set->externalId, language: $set->language));
                }

                ++$setPosition;
                $lock->refresh(self::LOCK_TTL_SECONDS);
            }

            $missingSetIds = array_values(array_diff($request->onlySetIds, array_keys($returnedSetIds)));
            if ($missingSetIds !== []) {
                $message = sprintf(
                    'Language "%s" is missing requested set "%s".',
                    $language,
                    $missingSetIds[0],
                );
                throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: sprintf('missing-set:%s:%s', $language, $missingSetIds[0]), entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::MissingSet, severity: CatalogDiscrepancySeverity::Critical, message: $message, externalReference: $missingSetIds[0], language: $language));
            }
        }

        if ($setPosition === 0) {
            $language = count($request->languages) === 1 ? $request->languages[0] : null;
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'empty-provider-scope', entityType: CatalogDiscrepancyEntityType::Scope, type: CatalogDiscrepancyType::MissingSet, severity: CatalogDiscrepancySeverity::Critical, message: 'The provider returned no sets for the requested catalog scope.', language: $language));
        }

        return [$setPosition, $cardCount];
    }

    private function completedDuplicate(CatalogImportRun $run): ?CatalogImportRun
    {
        $checksum = $run->getContentChecksum()
            ?? throw new RuntimeException('A validating catalog import requires a content checksum.');
        $sourceVersion = $run->getSourceVersion();
        if ($sourceVersion === null) {
            return null;
        }

        $versionMatch = $this->runs->findCompletedBySourceVersion(
            $run->getProvider(),
            $run->getScopeKey(),
            $sourceVersion,
        );
        if (!$versionMatch instanceof CatalogImportRun) {
            return null;
        }
        if ($versionMatch->getContentChecksum() !== $checksum) {
            $message = sprintf(
                'Source version "%s" produced a different catalog checksum.',
                $sourceVersion,
            );
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'conflicting-source-version:'.$sourceVersion, entityType: CatalogDiscrepancyEntityType::Scope, type: CatalogDiscrepancyType::ConflictingSourceVersion, severity: CatalogDiscrepancySeverity::Critical, message: $message, language: $this->singleRunLanguage($run)));
        }

        return $versionMatch;
    }

    /**
     * @param callable(string, string, int): void|null $reportSet
     */
    private function publish(
        ImportCatalogRequest $request,
        CatalogImportRun $run,
        LockInterface $lock,
        ?callable $reportSet,
    ): CatalogImportResult {
        $result = new CatalogImportResult();
        $publishedSets = 0;
        $publishedCards = 0;
        $runId = $run->getId()->toRfc4122();

        foreach ($this->staging->sets($runId) as $stagedSet) {
            $setResult = $this->store->runInTransaction(
                function () use (
                    $request,
                    $run,
                    $stagedSet,
                    $runId,
                    $publishedSets,
                    $publishedCards,
                ): CatalogImportResult {
                    $setResult = $this->publishSet($request, $run, $stagedSet);
                    $setCardCount = $setResult->getCreatedCards() + $setResult->getSyncedCards();
                    $this->runs->updatePublicationProgress(
                        $runId,
                        $publishedSets + 1,
                        $publishedCards + $setCardCount,
                    );

                    return $setResult;
                },
            );
            $result->merge($setResult);
            ++$publishedSets;
            $publishedCards += $setResult->getCreatedCards() + $setResult->getSyncedCards();
            $lock->refresh(self::LOCK_TTL_SECONDS);

            if ($reportSet !== null) {
                $reportSet($stagedSet->language, $stagedSet->externalId, count($stagedSet->cardExternalIds));
            }
        }

        return $result;
    }

    private function publishSet(
        ImportCatalogRequest $request,
        CatalogImportRun $run,
        ImportedCardSet $importedSet,
    ): CatalogImportResult {
        $result = new CatalogImportResult();
        $set = $this->importSet($run->getProvider(), $importedSet, $result);
        $this->store->flush();
        $pendingCards = 0;

        foreach ($this->staging->cards(
            $run->getId()->toRfc4122(),
            $importedSet->language,
            $importedSet->externalId,
        ) as $importedCard) {
            $this->importCard($run->getProvider(), $set, $importedCard, $result);
            ++$pendingCards;
            if ($pendingCards < $request->batchSize) {
                continue;
            }

            $this->store->flush();
            $this->store->clear();
            $set = $this->store->findSetByCode($this->validator->setCode($run->getProvider(), $importedSet))
                ?? throw new RuntimeException('The imported card set could not be reloaded.');
            $pendingCards = 0;
        }

        $this->store->flush();
        $this->store->clear();

        return $result;
    }

    private function importSet(
        string $provider,
        ImportedCardSet $importedSet,
        CatalogImportResult $result,
    ): CardSet {
        $setCode = $this->validator->setCode($provider, $importedSet);
        $existingSet = $this->store->findSetByCode($setCode);
        if ($existingSet instanceof CardSet) {
            $status = $existingSet->getStatus() === CardSetStatus::OutOfPrint
                ? CardSetStatus::OutOfPrint
                : $this->statusForReleaseDate($importedSet->releaseDate);
            $existingSet->synchronizeFromProvider(
                name: $importedSet->name,
                printedTotal: $importedSet->printedTotal,
                total: $importedSet->total,
                status: $status,
                releaseDate: $importedSet->releaseDate,
                logoUrl: $importedSet->logoUrl,
                symbolUrl: $importedSet->symbolUrl,
            );
            $this->store->persist($existingSet);
            $result->recordExistingSet();

            return $existingSet;
        }

        $seriesSourceKey = sprintf('%s:series:%s', $provider, mb_strtolower($importedSet->seriesExternalId));
        $series = $this->store->findSeriesBySourceKey($seriesSourceKey);
        if (!$series instanceof CardSeries) {
            $existingSeriesWithName = $this->store->findSeriesByName($importedSet->seriesName);
            if ($existingSeriesWithName instanceof CardSeries && $existingSeriesWithName->getSourceKey() === null) {
                $series = $existingSeriesWithName;
            } else {
                $seriesName = $existingSeriesWithName instanceof CardSeries
                    ? sprintf('%s [%s]', $importedSet->seriesName, $importedSet->seriesExternalId)
                    : $importedSet->seriesName;
                $series = new CardSeries($seriesName, $seriesSourceKey);
                $this->store->persist($series);
            }
        }

        $set = new CardSet(
            series: $series,
            name: $importedSet->name,
            code: $setCode,
            language: $importedSet->language,
            printedTotal: $importedSet->printedTotal,
            total: $importedSet->total,
            status: $this->statusForReleaseDate($importedSet->releaseDate),
            releaseDate: $importedSet->releaseDate,
            logoUrl: $importedSet->logoUrl,
            symbolUrl: $importedSet->symbolUrl,
        );
        $this->store->persist($set);
        $result->recordCreatedSet();

        return $set;
    }

    private function importCard(
        string $provider,
        CardSet $set,
        ImportedCatalogCard $importedCard,
        CatalogImportResult $result,
    ): void {
        $externalId = $this->cardExternalId($importedCard);
        $card = $this->store->findCardByExternalReference($provider, $externalId);
        if (!$card instanceof CatalogCard) {
            $card = new CatalogCard(
                set: $set,
                name: $importedCard->name,
                number: $importedCard->number,
                rarity: $importedCard->rarity,
                cardType: $importedCard->cardType,
                totalNumberInSet: $importedCard->totalNumberInSet,
                primaryCollectible: $importedCard->primaryCollectible,
                illustrator: $importedCard->illustrator,
                imageUrl: $importedCard->imageUrl,
                thumbnailUrl: $importedCard->thumbnailUrl,
                releaseDate: $importedCard->releaseDate ?? $set->getReleaseDate(),
                metadata: $importedCard->metadata,
            );

            foreach ($importedCard->variants as $variant) {
                $card->addVariant($variant);
            }

            $this->attachImportedArtist($card, $importedCard->illustrator);
            $card->addExternalReference($provider, $externalId, $importedCard->rawData);
            $this->store->persist($card);
            $result->recordCreatedCard();

            return;
        }

        foreach ($card->getExternalReferences() as $reference) {
            if ($reference->getSourceName() !== $provider || $reference->getExternalId() !== $externalId) {
                continue;
            }

            if ($importedCard->isDetailed) {
                if ($reference->getCorrectedData() === null) {
                    $card->synchronizeFromProvider(
                        name: $importedCard->name,
                        rarity: $importedCard->rarity,
                        cardType: $importedCard->cardType,
                        primaryCollectible: $importedCard->primaryCollectible,
                        illustrator: $importedCard->illustrator,
                        imageUrl: $importedCard->imageUrl,
                        thumbnailUrl: $importedCard->thumbnailUrl,
                        variants: $importedCard->variants,
                        metadata: $importedCard->metadata,
                    );
                    $this->attachImportedArtist($card, $importedCard->illustrator);
                }
                $reference->markSynced($importedCard->rawData);
            } else {
                if ($reference->getCorrectedData() === null) {
                    $card->synchronizeSummaryFromProvider(
                        name: $importedCard->name,
                        imageUrl: $importedCard->imageUrl,
                        thumbnailUrl: $importedCard->thumbnailUrl,
                    );
                }
                if (!$this->containsDetailedSourceData($reference->getRawData())) {
                    $reference->markSynced($importedCard->rawData);
                }
            }
            $this->store->persist($card);
            $result->recordSyncedCard();

            return;
        }

        $card->addExternalReference($provider, $externalId, $importedCard->rawData);
        $this->store->persist($card);
        $result->recordSyncedCard();
    }

    private function cardExternalId(ImportedCatalogCard $card): string
    {
        return sprintf('%s:%s', $card->language, $card->externalId);
    }

    /** @param array<string, mixed> $rawData */
    private function containsDetailedSourceData(array $rawData): bool
    {
        return array_key_exists('category', $rawData) || array_key_exists('variants', $rawData);
    }

    private function attachImportedArtist(CatalogCard $card, ?string $illustrator): void
    {
        if ($illustrator === null || Artist::canonicalizeName($illustrator) === '') {
            return;
        }

        $normalizedName = Artist::normalizeName($illustrator);
        $artist = $this->store->findArtistByNormalizedName($normalizedName);
        if (!$artist instanceof Artist) {
            $artist = Artist::fromCanonicalName($illustrator);
            $this->store->persist($artist);
        }

        $card->addArtist($artist);
    }

    private function statusForReleaseDate(?DateTimeImmutable $releaseDate): CardSetStatus
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Amsterdam'));

        return $releaseDate instanceof DateTimeImmutable && $releaseDate > $today
            ? CardSetStatus::Announced
            : CardSetStatus::Active;
    }

    private function recordFailure(string $runId, Throwable $exception): void
    {
        $this->store->clear();
        $failedRun = $this->runs->get($runId);
        if (!$failedRun instanceof CatalogImportRun || $failedRun->getStatus()->isTerminal()) {
            return;
        }
        if ($failedRun->getStatus() === CatalogImportStatus::Running) {
            $stagedCounts = $this->staging->counts($runId);
            $failedRun->recordStagedCounts($stagedCounts['sets'], $stagedCounts['cards']);
        }

        $safeReason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $exception->getMessage());
        $failureSummary = is_string($safeReason) && trim($safeReason) !== ''
            ? mb_substr(trim($safeReason), 0, 2_000)
            : 'The catalog provider failed without a safe error summary.';
        $failedRun->fail($failureSummary);
        $this->runs->save($failedRun);

        $observation = $exception instanceof CatalogImportValidationFailed
            ? $exception->observation
            : null;
        $this->discrepancyRecorder->record(
            $failedRun,
            $observation ?? new CatalogDiscrepancyObservation(
                identity: sprintf(
                    'provider-error:%s:%s',
                    $exception::class,
                    hash('sha256', $failureSummary),
                ),
                entityType: CatalogDiscrepancyEntityType::Import,
                type: CatalogDiscrepancyType::ProviderError,
                severity: CatalogDiscrepancySeverity::High,
                message: $failureSummary,
                language: $this->singleRunLanguage($failedRun),
            ),
        );
    }

    private function singleRunLanguage(CatalogImportRun $run): ?string
    {
        $languages = $run->getDataScope()['languages'] ?? null;

        return is_array($languages) && count($languages) === 1 && is_string($languages[0])
            ? $languages[0]
            : null;
    }
}
