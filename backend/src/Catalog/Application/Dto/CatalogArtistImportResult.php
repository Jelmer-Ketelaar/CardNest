<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

final class CatalogArtistImportResult
{
    private const int SAMPLE_LIMIT = 25;

    private int $processedArtists = 0;

    private int $createdArtists = 0;

    private int $existingArtists = 0;

    private int $providerCardReferences = 0;

    private int $linkedCards = 0;

    private int $existingCardLinks = 0;

    private int $backfilledCards = 0;

    private int $missingCards = 0;

    private int $conflictingCards = 0;

    /** @var list<string> */
    private array $missingCardSamples = [];

    /** @var list<string> */
    private array $conflictingCardSamples = [];

    public function recordProcessedArtist(int $cardReferenceCount): void
    {
        ++$this->processedArtists;
        $this->providerCardReferences += $cardReferenceCount;
    }

    public function recordCreatedArtist(): void
    {
        ++$this->createdArtists;
    }

    public function recordExistingArtist(): void
    {
        ++$this->existingArtists;
    }

    public function recordLinkedCard(): void
    {
        ++$this->linkedCards;
    }

    public function recordExistingCardLink(): void
    {
        ++$this->existingCardLinks;
    }

    public function recordBackfilledCard(): void
    {
        ++$this->backfilledCards;
    }

    public function recordMissingCard(string $externalReference): void
    {
        ++$this->missingCards;
        if (count($this->missingCardSamples) < self::SAMPLE_LIMIT) {
            $this->missingCardSamples[] = $externalReference;
        }
    }

    public function recordConflictingCard(string $externalReference): void
    {
        ++$this->conflictingCards;
        if (count($this->conflictingCardSamples) < self::SAMPLE_LIMIT) {
            $this->conflictingCardSamples[] = $externalReference;
        }
    }

    public function getProcessedArtists(): int
    {
        return $this->processedArtists;
    }

    public function getCreatedArtists(): int
    {
        return $this->createdArtists;
    }

    public function getExistingArtists(): int
    {
        return $this->existingArtists;
    }

    public function getProviderCardReferences(): int
    {
        return $this->providerCardReferences;
    }

    public function getLinkedCards(): int
    {
        return $this->linkedCards;
    }

    public function getExistingCardLinks(): int
    {
        return $this->existingCardLinks;
    }

    public function getBackfilledCards(): int
    {
        return $this->backfilledCards;
    }

    public function getMissingCards(): int
    {
        return $this->missingCards;
    }

    public function getConflictingCards(): int
    {
        return $this->conflictingCards;
    }

    /** @return list<string> */
    public function getMissingCardSamples(): array
    {
        return $this->missingCardSamples;
    }

    /** @return list<string> */
    public function getConflictingCardSamples(): array
    {
        return $this->conflictingCardSamples;
    }
}
