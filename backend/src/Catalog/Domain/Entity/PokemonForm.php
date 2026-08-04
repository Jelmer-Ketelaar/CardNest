<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\PokemonFormName;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pokemon_forms')]
#[ORM\UniqueConstraint(
    name: 'uniq_pokemon_form_normalized_name',
    columns: ['species_id', 'normalized_name'],
)]
#[ORM\UniqueConstraint(name: 'uniq_pokemon_form_slug', columns: ['species_id', 'slug'])]
#[ORM\UniqueConstraint(
    name: 'uniq_pokemon_form_default_per_species',
    columns: ['species_id'],
    options: ['where' => '(is_default = true)'],
)]
final class PokemonForm
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var Collection<int, PokemonFormExternalReference> */
    #[ORM\OneToMany(
        mappedBy: 'form',
        targetEntity: PokemonFormExternalReference::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $externalReferences;

    #[ORM\Column(name: 'canonical_name', length: 180)]
    private readonly string $canonicalName;

    #[ORM\Column(name: 'normalized_name', length: 180)]
    private readonly string $normalizedName;

    #[ORM\Column(length: 220)]
    private readonly string $slug;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: PokemonSpecies::class, inversedBy: 'forms')]
        #[ORM\JoinColumn(name: 'species_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PokemonSpecies $species,
        string $canonicalName,
        #[ORM\Column(name: 'form_type', length: 30, enumType: PokemonFormType::class)]
        private readonly PokemonFormType $type,
        #[ORM\Column(name: 'is_default')]
        private readonly bool $isDefault,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (($type === PokemonFormType::Default) !== $isDefault) {
            throw new InvalidArgumentException('Only a default Pokémon form can be marked as default.');
        }

        $name = PokemonFormName::fromString($canonicalName);
        $this->id = Uuid::v7();
        $this->canonicalName = $name->getCanonical();
        $this->normalizedName = $name->getNormalized();
        $this->slug = $name->toSlug('form-'.$this->id->toRfc4122());
        $this->externalReferences = new ArrayCollection();
        $this->updatedAt = $createdAt;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getType(): PokemonFormType
    {
        return $this->type;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /** @return Collection<int, PokemonFormExternalReference> */
    public function getExternalReferences(): Collection
    {
        return $this->externalReferences;
    }

    public function recordExternalReference(
        string $provider,
        string $externalId,
        ?string $sourceVersion = null,
        DateTimeImmutable $syncedAt = new DateTimeImmutable(),
    ): PokemonFormExternalReference {
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

        $externalReference = new PokemonFormExternalReference(
            form: $this,
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
