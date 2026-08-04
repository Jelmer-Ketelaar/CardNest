<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Catalog\Application\Exception\CatalogImportValidationFailed;
use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Entity\VariantCode;
use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;

final readonly class CatalogImportValidator
{
    public function validateSet(string $provider, ImportedCardSet $set): void
    {
        $this->required($set->language, 10, 'set language');
        $this->required($set->externalId, 180, 'set external ID');
        $this->required($set->name, 180, 'set name');
        $this->required($set->seriesExternalId, 180, 'series external ID');
        $this->required($set->seriesName, 180, 'series name');
        $this->nullableLength($set->logoUrl, 1_024, 'set logo URL');
        $this->nullableLength($set->symbolUrl, 1_024, 'set symbol URL');

        if ($set->printedTotal < 0 || $set->total < 0 || $set->printedTotal > $set->total) {
            $message = sprintf(
                'Set "%s:%s" contains invalid printed or total card counts.',
                $set->language,
                $set->externalId,
            );
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'conflicting-total:'.$set->language.':'.$set->externalId, entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::ConflictingTotal, severity: CatalogDiscrepancySeverity::Critical, message: $message, externalReference: $set->externalId, language: $set->language));
        }
        if (count($set->cardExternalIds) !== $set->total) {
            $message = sprintf(
                'Set "%s:%s" declares %d cards but provides %d card references.',
                $set->language,
                $set->externalId,
                $set->total,
                count($set->cardExternalIds),
            );
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'declared-total:'.$set->language.':'.$set->externalId, entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::ConflictingTotal, severity: CatalogDiscrepancySeverity::Critical, message: $message, externalReference: $set->externalId, language: $set->language));
        }
        if (count(array_unique($set->cardExternalIds)) !== count($set->cardExternalIds)) {
            $message = sprintf(
                'Set "%s:%s" contains duplicate card references.',
                $set->language,
                $set->externalId,
            );
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'duplicate-card-reference:'.$set->language.':'.$set->externalId, entityType: CatalogDiscrepancyEntityType::Set, type: CatalogDiscrepancyType::DuplicateCard, severity: CatalogDiscrepancySeverity::Critical, message: $message, externalReference: $set->externalId, language: $set->language));
        }
        foreach ($set->cardExternalIds as $cardExternalId) {
            $this->required($cardExternalId, 170, 'card external ID');
        }

        $sourceKey = sprintf('%s:series:%s', $provider, mb_strtolower($set->seriesExternalId));
        if (mb_strlen($sourceKey) > 100) {
            throw new CatalogImportValidationFailed('The normalized series source key exceeds 100 characters.');
        }
        if (mb_strlen($this->setCode($provider, $set)) > 50) {
            throw new CatalogImportValidationFailed('The normalized set code exceeds 50 characters.');
        }
    }

    /** @param array<string, true> $expectedCardIds */
    public function validateCard(
        string $provider,
        ImportedCardSet $set,
        ImportedCatalogCard $card,
        array $expectedCardIds,
    ): void {
        if ($card->language !== $set->language || $card->setExternalId !== $set->externalId) {
            $message = sprintf(
                'Card "%s" does not belong to staged set "%s:%s".',
                $card->externalId,
                $set->language,
                $set->externalId,
            );
            throw new CatalogImportValidationFailed($message);
        }
        if (!isset($expectedCardIds[$card->externalId])) {
            $message = sprintf(
                'Card "%s" is absent from the source set card references.',
                $card->externalId,
            );
            throw new CatalogImportValidationFailed($message);
        }

        $this->required($card->externalId, 170, 'card external ID');
        $this->required($card->name, 180, 'card name');
        $this->required($card->number, 50, 'card display number');
        $this->required($card->rarity, 100, 'card rarity');
        $this->required($card->cardType, 100, 'card type');
        $this->nullableLength($card->totalNumberInSet, 50, 'card printed total');
        $this->nullableLength($card->primaryCollectible, 180, 'card primary collectible');
        $this->nullableLength($card->illustrator, 180, 'card illustrator');
        $this->nullableLength($card->imageUrl, 1_024, 'card image URL');
        $this->nullableLength($card->thumbnailUrl, 1_024, 'card thumbnail URL');

        if ($card->variants === []) {
            $message = sprintf('Card "%s" has no variants.', $card->externalId);
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'unknown-variant:'.$card->language.':'.$card->externalId, entityType: CatalogDiscrepancyEntityType::Card, type: CatalogDiscrepancyType::UnknownVariant, severity: CatalogDiscrepancySeverity::High, message: $message, externalReference: $card->externalId, language: $card->language));
        }
        $variantCodes = array_map(static fn (VariantCode $variant): string => $variant->value, $card->variants);
        if (count(array_unique($variantCodes)) !== count($variantCodes)) {
            $message = sprintf(
                'Card "%s" contains duplicate variants.',
                $card->externalId,
            );
            throw CatalogImportValidationFailed::observed(new CatalogDiscrepancyObservation(identity: 'duplicate-variant:'.$card->language.':'.$card->externalId, entityType: CatalogDiscrepancyEntityType::Card, type: CatalogDiscrepancyType::UnknownVariant, severity: CatalogDiscrepancySeverity::High, message: $message, externalReference: $card->externalId, language: $card->language));
        }
        if (mb_strlen(sprintf('%s:%s', $card->language, $card->externalId)) > 180) {
            throw new CatalogImportValidationFailed('The normalized card external reference exceeds 180 characters.');
        }
    }

    public function setCode(string $provider, ImportedCardSet $set): string
    {
        return sprintf('%s:%s:%s', $provider, $set->language, $set->externalId);
    }

    private function required(string $value, int $maxLength, string $field): void
    {
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            $message = sprintf(
                'The normalized %s must contain 1 to %d trimmed characters.',
                $field,
                $maxLength,
            );
            throw new CatalogImportValidationFailed($message);
        }
    }

    private function nullableLength(?string $value, int $maxLength, string $field): void
    {
        if ($value === null) {
            return;
        }
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            $message = sprintf(
                'The normalized %s must be null or contain 1 to %d trimmed characters.',
                $field,
                $maxLength,
            );
            throw new CatalogImportValidationFailed($message);
        }
    }
}
