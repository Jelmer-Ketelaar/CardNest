<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\PokemonName;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pokemon_species_aliases')]
#[ORM\UniqueConstraint(
    name: 'uniq_pokemon_species_alias_normalized',
    columns: ['species_id', 'normalized_name'],
)]
#[ORM\Index(name: 'idx_pokemon_species_alias_normalized', columns: ['normalized_name'])]
final class PokemonSpeciesAlias
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: PokemonSpecies::class, inversedBy: 'aliases')]
    #[ORM\JoinColumn(name: 'species_id', nullable: false, onDelete: 'CASCADE')]
    private readonly PokemonSpecies $species;

    #[ORM\Column(name: 'canonical_name', length: 180)]
    private readonly string $canonicalName;

    #[ORM\Column(name: 'normalized_name', length: 180)]
    private readonly string $normalizedName;

    #[ORM\Column(name: 'alias_type', length: 30, enumType: PokemonSpeciesAliasType::class)]
    private readonly PokemonSpeciesAliasType $type;

    #[ORM\Column(length: 10, nullable: true)]
    private readonly ?string $language;

    #[ORM\Column(length: 100)]
    private readonly string $source;

    #[ORM\Column(name: 'source_reference', length: 500, nullable: true)]
    private readonly ?string $sourceReference;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $createdAt;

    public function __construct(
        PokemonSpecies $species,
        string $name,
        PokemonSpeciesAliasType $type,
        string $source,
        ?string $language = null,
        ?string $sourceReference = null,
        DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $aliasName = PokemonName::fromString($name);
        $this->id = Uuid::v7();
        $this->species = $species;
        $this->canonicalName = $aliasName->getCanonical();
        $this->normalizedName = $aliasName->getNormalized();
        $this->type = $type;
        $this->language = self::normalizeLanguage($language);
        $this->source = PokemonSpeciesExternalReference::normalizeProvider($source);
        $this->sourceReference = self::normalizeSourceReference($sourceReference);
        $this->createdAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSpecies(): PokemonSpecies
    {
        return $this->species;
    }

    public function getCanonicalName(): string
    {
        return $this->canonicalName;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName;
    }

    public function getType(): PokemonSpeciesAliasType
    {
        return $this->type;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function normalizeLanguage(?string $language): ?string
    {
        if ($language === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($language));
        if (
            $normalized === ''
            || mb_strlen($normalized) > 10
            || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $normalized) !== 1
        ) {
            throw new InvalidArgumentException('A Pokémon alias language must be a supported language tag.');
        }

        return $normalized;
    }

    private static function normalizeSourceReference(?string $sourceReference): ?string
    {
        if ($sourceReference === null) {
            return null;
        }

        $normalized = trim($sourceReference);
        if ($normalized === '' || mb_strlen($normalized) > 500) {
            throw new InvalidArgumentException('A Pokémon alias source reference must be non-empty and bounded.');
        }
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1) {
            throw new InvalidArgumentException('A Pokémon alias source reference cannot contain control characters.');
        }

        return $normalized;
    }
}
