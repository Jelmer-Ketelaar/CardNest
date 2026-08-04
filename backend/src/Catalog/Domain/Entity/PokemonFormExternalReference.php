<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pokemon_form_external_references')]
#[ORM\UniqueConstraint(
    name: 'uniq_pokemon_form_external_reference',
    columns: ['provider', 'external_id'],
)]
#[ORM\Index(name: 'idx_pokemon_form_external_form', columns: ['form_id'])]
final class PokemonFormExternalReference
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'source_version', length: 180, nullable: true)]
    private ?string $sourceVersion;

    #[ORM\ManyToOne(targetEntity: PokemonForm::class, inversedBy: 'externalReferences')]
    #[ORM\JoinColumn(name: 'form_id', nullable: false, onDelete: 'CASCADE')]
    private readonly PokemonForm $form;

    #[ORM\Column(length: 100)]
    private readonly string $provider;

    #[ORM\Column(name: 'external_id', length: 180)]
    private readonly string $externalId;

    #[ORM\Column(name: 'imported_at', type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $importedAt;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        PokemonForm $form,
        string $provider,
        string $externalId,
        ?string $sourceVersion = null,
        DateTimeImmutable $importedAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->form = $form;
        $this->provider = PokemonSpeciesExternalReference::normalizeProvider($provider);
        $this->externalId = PokemonSpeciesExternalReference::normalizeExternalId($externalId);
        $this->sourceVersion = self::normalizeSourceVersion($sourceVersion);
        $this->importedAt = $importedAt;
        $this->lastSyncedAt = $importedAt;
        $this->createdAt = $importedAt;
        $this->updatedAt = $importedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getForm(): PokemonForm
    {
        return $this->form;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getSourceVersion(): ?string
    {
        return $this->sourceVersion;
    }

    public function getImportedAt(): DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function getLastSyncedAt(): DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markSynced(
        ?string $sourceVersion,
        DateTimeImmutable $syncedAt = new DateTimeImmutable(),
    ): void {
        if ($syncedAt < $this->lastSyncedAt) {
            throw new InvalidArgumentException('A Pokémon form sync cannot predate its latest synchronization.');
        }

        $normalizedSourceVersion = self::normalizeSourceVersion($sourceVersion);
        if ($normalizedSourceVersion !== null) {
            $this->sourceVersion = $normalizedSourceVersion;
        }
        if ($syncedAt > $this->lastSyncedAt) {
            $this->lastSyncedAt = $syncedAt;
            $this->updatedAt = $syncedAt;
        }
    }

    private static function normalizeSourceVersion(?string $sourceVersion): ?string
    {
        if ($sourceVersion === null) {
            return null;
        }

        $normalized = trim($sourceVersion);
        if ($normalized === '' || mb_strlen($normalized) > 180) {
            throw new InvalidArgumentException('A Pokémon form source version must be non-empty and bounded.');
        }
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1) {
            throw new InvalidArgumentException('A Pokémon form source version cannot contain control characters.');
        }

        return $normalized;
    }
}
