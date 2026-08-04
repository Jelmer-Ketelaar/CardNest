<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\PokemonName;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pokemon_species')]
#[ORM\UniqueConstraint(name: 'uniq_pokemon_species_national_dex', columns: ['national_dex_number'])]
#[ORM\UniqueConstraint(name: 'uniq_pokemon_species_normalized_name', columns: ['normalized_name'])]
#[ORM\UniqueConstraint(name: 'uniq_pokemon_species_slug', columns: ['slug'])]
final class PokemonSpecies
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'national_dex_number')]
    private readonly int $nationalDexNumber;

    #[ORM\Column(name: 'canonical_name', length: 180)]
    private readonly string $canonicalName;

    #[ORM\Column(name: 'normalized_name', length: 180)]
    private readonly string $normalizedName;

    #[ORM\Column(length: 220)]
    private readonly string $slug;

    #[ORM\Column]
    private readonly int $generation;

    #[ORM\Column(name: 'introduced_at', type: Types::DATE_IMMUTABLE, nullable: true)]
    private readonly ?DateTimeImmutable $introducedAt;

    /** @var Collection<int, PokemonSpeciesAlias> */
    #[ORM\OneToMany(
        mappedBy: 'species',
        targetEntity: PokemonSpeciesAlias::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $aliases;

    /** @var Collection<int, PokemonSpeciesExternalReference> */
    #[ORM\OneToMany(
        mappedBy: 'species',
        targetEntity: PokemonSpeciesExternalReference::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $externalReferences;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $nationalDexNumber,
        string $canonicalName,
        int $generation,
        ?DateTimeImmutable $introducedAt = null,
        DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if ($nationalDexNumber < 1) {
            throw new InvalidArgumentException('A Pokémon National Dex number must be positive.');
        }
        if ($generation < 1) {
            throw new InvalidArgumentException('A Pokémon generation must be positive.');
        }

        $name = PokemonName::fromString($canonicalName);
        $this->id = Uuid::v7();
        $this->nationalDexNumber = $nationalDexNumber;
        $this->canonicalName = $name->getCanonical();
        $this->normalizedName = $name->getNormalized();
        $this->slug = $name->toSlug('pokemon-'.$nationalDexNumber);
        $this->generation = $generation;
        $this->introducedAt = $introducedAt;
        $this->aliases = new ArrayCollection();
        $this->externalReferences = new ArrayCollection();
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNationalDexNumber(): int
    {
        return $this->nationalDexNumber;
    }

    public function getCanonicalName(): string
    {
        return $this->canonicalName;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function getIntroducedAt(): ?DateTimeImmutable
    {
        return $this->introducedAt;
    }

    /** @return Collection<int, PokemonSpeciesAlias> */
    public function getAliases(): Collection
    {
        return $this->aliases;
    }

    public function addAlias(
        string $name,
        PokemonSpeciesAliasType $type,
        string $source,
        ?string $language = null,
        ?string $sourceReference = null,
        DateTimeImmutable $recordedAt = new DateTimeImmutable(),
    ): PokemonSpeciesAlias {
        $alias = new PokemonSpeciesAlias(
            species: $this,
            name: $name,
            type: $type,
            source: $source,
            language: $language,
            sourceReference: $sourceReference,
            createdAt: $recordedAt,
        );
        if ($alias->getNormalizedName() === $this->normalizedName) {
            throw new InvalidArgumentException('A Pokémon alias must differ from its canonical name.');
        }

        foreach ($this->aliases as $existingAlias) {
            if ($existingAlias->getNormalizedName() === $alias->getNormalizedName()) {
                return $existingAlias;
            }
        }

        $this->aliases->add($alias);
        $this->touch($recordedAt);

        return $alias;
    }

    /** @return Collection<int, PokemonSpeciesExternalReference> */
    public function getExternalReferences(): Collection
    {
        return $this->externalReferences;
    }

    public function recordExternalReference(
        string $provider,
        string $externalId,
        ?string $sourceVersion = null,
        DateTimeImmutable $syncedAt = new DateTimeImmutable(),
    ): PokemonSpeciesExternalReference {
        $normalizedProvider = PokemonSpeciesExternalReference::normalizeProvider($provider);
        $normalizedExternalId = PokemonSpeciesExternalReference::normalizeExternalId($externalId);

        foreach ($this->externalReferences as $externalReference) {
            if (
                $externalReference->getProvider() === $normalizedProvider
                && $externalReference->getExternalId() === $normalizedExternalId
            ) {
                $externalReference->markSynced($sourceVersion, $syncedAt);
                $this->touch($syncedAt);

                return $externalReference;
            }
        }

        $externalReference = new PokemonSpeciesExternalReference(
            species: $this,
            provider: $normalizedProvider,
            externalId: $normalizedExternalId,
            sourceVersion: $sourceVersion,
            importedAt: $syncedAt,
        );
        $this->externalReferences->add($externalReference);
        $this->touch($syncedAt);

        return $externalReference;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt > $this->updatedAt) {
            $this->updatedAt = $occurredAt;
        }
    }
}
