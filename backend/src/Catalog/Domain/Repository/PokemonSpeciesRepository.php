<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\PokemonSpecies;

interface PokemonSpeciesRepository
{
    public function get(string $id): ?PokemonSpecies;

    public function findByNationalDexNumber(int $nationalDexNumber): ?PokemonSpecies;

    public function findBySlug(string $slug): ?PokemonSpecies;

    public function findByName(string $name): ?PokemonSpecies;

    public function findByExternalReference(string $provider, string $externalId): ?PokemonSpecies;

    public function save(PokemonSpecies $species): void;
}
