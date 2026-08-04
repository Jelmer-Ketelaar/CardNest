<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use InvalidArgumentException;

final readonly class CatalogDiscrepancyObservation
{
    public function __construct(
        public string $identity,
        public CatalogDiscrepancyEntityType $entityType,
        public CatalogDiscrepancyType $type,
        public CatalogDiscrepancySeverity $severity,
        public string $message,
        public ?string $externalReference = null,
        public ?string $language = null,
        public ?string $region = null,
    ) {
        $this->assertIdentifier($identity, 500, 'identity');
        $this->assertIdentifier($message, 2_000, 'message');
        $this->assertOptionalIdentifier($externalReference, 500, 'external reference');
        $this->assertOptionalIdentifier($language, 10, 'language');
        $this->assertOptionalIdentifier($region, 50, 'region');
    }

    public function fingerprint(string $provider, string $scopeKey): string
    {
        return hash('sha256', json_encode([
            'provider' => $provider,
            'scopeKey' => $scopeKey,
            'identity' => $this->identity,
            'language' => $this->language,
            'region' => $this->region,
            'entityType' => $this->entityType->value,
            'type' => $this->type->value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function assertIdentifier(string $value, int $maxLength, string $field): void
    {
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            throw new InvalidArgumentException(sprintf('The discrepancy observation %s is invalid.', $field));
        }
    }

    private function assertOptionalIdentifier(?string $value, int $maxLength, string $field): void
    {
        if ($value === null) {
            return;
        }

        $this->assertIdentifier($value, $maxLength, $field);
    }
}
