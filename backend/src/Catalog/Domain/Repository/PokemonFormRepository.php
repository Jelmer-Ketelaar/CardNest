<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\PokemonForm;
use App\Catalog\Domain\Entity\PokemonSpecies;

interface PokemonFormRepository
{
    public function get(string $id): ?PokemonForm;

    /** @return list<PokemonForm> */
    public function findBySpecies(PokemonSpecies $species): array;

    public function findBySpeciesAndName(PokemonSpecies $species, string $name): ?PokemonForm;

    public function findBySpeciesAndSlug(PokemonSpecies $species, string $slug): ?PokemonForm;

    public function findDefaultForSpecies(PokemonSpecies $species): ?PokemonForm;

    public function findByExternalReference(string $provider, string $externalId): ?PokemonForm;
}
