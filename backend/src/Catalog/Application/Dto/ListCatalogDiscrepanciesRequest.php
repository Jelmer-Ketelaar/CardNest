<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Repository\CatalogDiscrepancySearchCriteria;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCatalogDiscrepanciesRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['open', 'accepted', 'resolved', 'ignored_with_reason', 'blocked_external'])]
        public ?string $status = null,
        #[Assert\Choice(choices: ['critical', 'high', 'medium', 'low', 'informational'])]
        public ?string $severity = null,
        #[Assert\Choice(choices: [
            'missing_set',
            'missing_card',
            'missing_pokemon',
            'missing_form',
            'missing_artist',
            'missing_image',
            'missing_card_number',
            'duplicate_card',
            'duplicate_set',
            'conflicting_name',
            'conflicting_number',
            'conflicting_release_date',
            'conflicting_total',
            'conflicting_source_version',
            'orphan_card',
            'unmapped_pokemon',
            'low_resolution_image',
            'unknown_variant',
            'provider_error',
        ])]
        public ?string $type = null,
        #[Assert\Length(max: 100)]
        public ?string $provider = null,
        #[Assert\Length(max: 10)]
        public ?string $language = null,
        #[Assert\Length(max: 50)]
        public ?string $region = null,
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 50,
    ) {
    }

    public function toCriteria(): CatalogDiscrepancySearchCriteria
    {
        return new CatalogDiscrepancySearchCriteria(
            status: $this->status !== null ? CatalogDiscrepancyResolutionStatus::from($this->status) : null,
            severity: $this->severity !== null ? CatalogDiscrepancySeverity::from($this->severity) : null,
            type: $this->type !== null ? CatalogDiscrepancyType::from($this->type) : null,
            provider: $this->provider,
            language: $this->language,
            region: $this->region,
            page: $this->page,
            perPage: $this->perPage,
        );
    }
}
