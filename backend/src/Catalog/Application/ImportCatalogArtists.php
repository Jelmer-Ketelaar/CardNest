<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogArtistImportResult;
use App\Catalog\Application\Dto\ImportCatalogArtistsRequest;
use App\Catalog\Application\Import\CatalogArtistImportStore;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Provider\CatalogArtistProvider;
use LogicException;
use RuntimeException;

final readonly class ImportCatalogArtists
{
    public function __construct(
        private CatalogArtistProvider $provider,
        private CatalogArtistImportStore $store,
    ) {
    }

    /** @return list<string> */
    public function availableLanguages(): array
    {
        return $this->store->catalogLanguages();
    }

    /**
     * @param callable(string, string, int): void|null $reportArtist
     */
    public function execute(
        ImportCatalogArtistsRequest $request,
        ?callable $reportArtist = null,
    ): CatalogArtistImportResult {
        $result = new CatalogArtistImportResult();
        $this->backfillLegacyIllustrators($request->batchSize, $result);

        foreach ($request->languages as $language) {
            $groups = $this->provider->importArtistCardLinks($language, $request->onlyArtistNames);
            foreach ($groups as $group) {
                if ($group->language !== $language) {
                    throw new RuntimeException('The artist provider returned data for a different language.');
                }

                $result->recordProcessedArtist(count($group->cardExternalIds));
                $artistCache = [];
                $pendingSlugs = [];
                $artist = $this->resolveArtist(
                    $group->canonicalName,
                    $result,
                    $artistCache,
                    $pendingSlugs,
                );
                $artist->recordExternalReference(
                    $this->provider->getProviderName(),
                    $language,
                    $group->sourceReference,
                );
                $this->store->persist($artist);
                $this->store->flush();
                $this->store->clear();

                foreach (array_chunk($group->cardExternalIds, $request->batchSize) as $cardIdBatch) {
                    $this->linkCardBatch($language, $group->canonicalName, $cardIdBatch, $result);
                }

                if ($reportArtist !== null) {
                    $reportArtist($language, $group->canonicalName, count($group->cardExternalIds));
                }
            }
        }

        return $result;
    }

    private function backfillLegacyIllustrators(int $batchSize, CatalogArtistImportResult $result): void
    {
        while (true) {
            $cards = $this->store->findUnlinkedCardsWithIllustrator($batchSize);
            if ($cards === []) {
                return;
            }

            $artistCache = [];
            $pendingSlugs = [];
            foreach ($cards as $card) {
                $illustrator = $card->getIllustrator();
                if ($illustrator === null || Artist::canonicalizeName($illustrator) === '') {
                    continue;
                }

                $artist = $this->resolveArtist($illustrator, $result, $artistCache, $pendingSlugs);
                $card->synchronizePrimaryArtist($artist);
                $this->store->persist($card);
                $result->recordBackfilledCard();
            }

            $this->store->flush();
            $this->store->clear();
        }
    }

    /** @param non-empty-list<string> $cardExternalIds */
    private function linkCardBatch(
        string $language,
        string $artistName,
        array $cardExternalIds,
        CatalogArtistImportResult $result,
    ): void {
        $normalizedName = Artist::normalizeName($artistName);
        $artist = $this->store->findArtistByNormalizedName($normalizedName);
        if (!$artist instanceof Artist) {
            throw new RuntimeException(sprintf('Imported artist "%s" could not be reloaded.', $artistName));
        }

        $externalReferences = array_map(
            static function (string $cardId) use ($language): string {
                $externalReference = sprintf('%s:%s', $language, $cardId);
                if (mb_strlen($externalReference) > 180) {
                    throw new RuntimeException('An imported artist card reference exceeds the catalog reference limit.');
                }

                return $externalReference;
            },
            $cardExternalIds,
        );
        $cards = $this->store->findCardsByExternalReferences(
            $this->provider->getProviderName(),
            $externalReferences,
        );

        foreach ($externalReferences as $externalReference) {
            $card = $cards[$externalReference] ?? null;
            if ($card === null) {
                $result->recordMissingCard(sprintf(
                    '%s:%s',
                    $this->provider->getProviderName(),
                    $externalReference,
                ));

                continue;
            }

            try {
                $created = $card->synchronizePrimaryArtist($artist);
            } catch (LogicException) {
                $result->recordConflictingCard(sprintf(
                    '%s:%s',
                    $this->provider->getProviderName(),
                    $externalReference,
                ));

                continue;
            }

            $this->store->persist($card);
            if ($created) {
                $result->recordLinkedCard();
            } else {
                $result->recordExistingCardLink();
            }
        }

        $this->store->flush();
        $this->store->clear();
    }

    /**
     * @param array<string, Artist> $artistCache
     * @param array<string, string> $pendingSlugs
     */
    private function resolveArtist(
        string $canonicalName,
        CatalogArtistImportResult $result,
        array &$artistCache,
        array &$pendingSlugs,
    ): Artist {
        $canonicalName = Artist::canonicalizeName($canonicalName);
        $normalizedName = Artist::normalizeName($canonicalName);
        if (isset($artistCache[$normalizedName])) {
            return $artistCache[$normalizedName];
        }

        $artist = $this->store->findArtistByNormalizedName($normalizedName);
        if ($artist instanceof Artist) {
            $artistCache[$normalizedName] = $artist;
            $pendingSlugs[$artist->getSlug()] = $normalizedName;
            $result->recordExistingArtist();

            return $artist;
        }

        $artist = Artist::fromCanonicalName($canonicalName);
        $slugOwner = $this->store->findArtistBySlug($artist->getSlug());
        $pendingSlugOwner = $pendingSlugs[$artist->getSlug()] ?? null;
        if (
            ($slugOwner instanceof Artist && $slugOwner->getNormalizedName() !== $normalizedName)
            || ($pendingSlugOwner !== null && $pendingSlugOwner !== $normalizedName)
        ) {
            $suffix = '-'.substr(hash('sha256', $normalizedName), 0, 8);
            $slug = mb_substr($artist->getSlug(), 0, 220 - mb_strlen($suffix)).$suffix;
            $slugOwner = $this->store->findArtistBySlug($slug);
            $pendingSlugOwner = $pendingSlugs[$slug] ?? null;
            if (
                ($slugOwner instanceof Artist && $slugOwner->getNormalizedName() !== $normalizedName)
                || ($pendingSlugOwner !== null && $pendingSlugOwner !== $normalizedName)
            ) {
                throw new RuntimeException(sprintf('Could not resolve a unique slug for artist "%s".', $canonicalName));
            }

            $artist = new Artist($canonicalName, $normalizedName, $slug);
        }

        $this->store->persist($artist);
        $artistCache[$normalizedName] = $artist;
        $pendingSlugs[$artist->getSlug()] = $normalizedName;
        $result->recordCreatedArtist();

        return $artist;
    }
}
