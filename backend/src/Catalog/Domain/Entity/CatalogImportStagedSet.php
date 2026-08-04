<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine schema record for normalized import staging. Runtime staging uses
 * bounded DBAL writes and never treats this record as live catalog data.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'catalog_import_staged_sets')]
#[ORM\UniqueConstraint(
    name: 'uniq_catalog_import_staged_set',
    columns: ['import_run_id', 'language', 'external_id'],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_catalog_import_staged_set_position',
    columns: ['import_run_id', 'position'],
)]
#[ORM\Index(name: 'idx_catalog_import_staged_sets_run', columns: ['import_run_id'])]
final class CatalogImportStagedSet
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private readonly Uuid $id,
        #[ORM\ManyToOne(targetEntity: CatalogImportRun::class)]
        #[ORM\JoinColumn(name: 'import_run_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CatalogImportRun $importRun,
        #[ORM\Column]
        private readonly int $position,
        #[ORM\Column(length: 10)]
        private readonly string $language,
        #[ORM\Column(name: 'external_id', length: 180)]
        private readonly string $externalId,
        #[ORM\Column(type: Types::JSONB)]
        private readonly array $payload,
        #[ORM\Column(name: 'record_checksum', length: 64)]
        private readonly string $recordChecksum,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }
}
