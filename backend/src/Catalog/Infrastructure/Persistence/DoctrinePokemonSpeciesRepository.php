<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\PokemonSpecies;
use App\Catalog\Domain\Entity\PokemonSpeciesExternalReference;
use App\Catalog\Domain\Repository\PokemonSpeciesRepository;
use App\Catalog\Domain\ValueObject\PokemonName;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrinePokemonSpeciesRepository implements PokemonSpeciesRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $id): ?PokemonSpecies
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        $species = $this->entityManager->find(PokemonSpecies::class, $uuid);

        return $species instanceof PokemonSpecies ? $species : null;
    }

    public function findByNationalDexNumber(int $nationalDexNumber): ?PokemonSpecies
    {
        $species = $this->entityManager->getRepository(PokemonSpecies::class)->findOneBy([
            'nationalDexNumber' => $nationalDexNumber,
        ]);

        return $species instanceof PokemonSpecies ? $species : null;
    }

    public function findBySlug(string $slug): ?PokemonSpecies
    {
        $species = $this->entityManager->getRepository(PokemonSpecies::class)->findOneBy([
            'slug' => mb_strtolower(trim($slug)),
        ]);

        return $species instanceof PokemonSpecies ? $species : null;
    }

    public function findByName(string $name): ?PokemonSpecies
    {
        try {
            $normalizedName = PokemonName::fromString($name)->getNormalized();
        } catch (InvalidArgumentException) {
            return null;
        }

        /** @var list<PokemonSpecies> $matches */
        $matches = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT species')
            ->from(PokemonSpecies::class, 'species')
            ->leftJoin('species.aliases', 'alias')
            ->where('species.normalizedName = :normalizedName')
            ->orWhere('alias.normalizedName = :normalizedName')
            ->setParameter('normalizedName', $normalizedName)
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function findByExternalReference(string $provider, string $externalId): ?PokemonSpecies
    {
        try {
            $normalizedProvider = PokemonSpeciesExternalReference::normalizeProvider($provider);
            $normalizedExternalId = PokemonSpeciesExternalReference::normalizeExternalId($externalId);
        } catch (InvalidArgumentException) {
            return null;
        }

        $species = $this->entityManager->createQueryBuilder()
            ->select('species')
            ->from(PokemonSpecies::class, 'species')
            ->innerJoin('species.externalReferences', 'reference')
            ->where('reference.provider = :provider')
            ->andWhere('reference.externalId = :externalId')
            ->setParameter('provider', $normalizedProvider)
            ->setParameter('externalId', $normalizedExternalId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $species instanceof PokemonSpecies ? $species : null;
    }

    public function save(PokemonSpecies $species): void
    {
        $this->entityManager->persist($species);
        $this->entityManager->flush();
    }
}
