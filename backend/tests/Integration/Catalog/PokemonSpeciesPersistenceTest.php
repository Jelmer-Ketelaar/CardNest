<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\PokemonSpecies;
use App\Catalog\Domain\Entity\PokemonSpeciesAliasType;
use App\Catalog\Domain\Entity\PokemonSpeciesExternalReference;
use App\Catalog\Domain\Repository\PokemonSpeciesRepository;
use App\Catalog\Infrastructure\Persistence\DoctrinePokemonSpeciesRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PokemonSpeciesPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PokemonSpeciesRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = new DoctrinePokemonSpeciesRepository($this->entityManager);
    }

    public function testItPersistsProvenanceAndFindsSpeciesByEverySupportedIdentity(): void
    {
        // Arrange
        $species = new PokemonSpecies(
            nationalDexNumber: 258,
            canonicalName: 'Mudkip',
            generation: 3,
            introducedAt: new DateTimeImmutable('2002-11-21'),
        );
        $species->addAlias(
            name: 'Mizugorou',
            type: PokemonSpeciesAliasType::Transliteration,
            source: 'tcgdex',
            language: 'ja',
            sourceReference: 'pokemon:258:ja',
        );
        $species->recordExternalReference('tcgdex', 'pokemon:258', '2026-08-04');

        // Act
        $this->repository->save($species);
        $speciesId = $species->getId()->toRfc4122();
        $this->entityManager->clear();
        $byId = $this->repository->get($speciesId);
        $byDex = $this->repository->findByNationalDexNumber(258);
        $bySlug = $this->repository->findBySlug('MUDKIP');
        $byCanonicalName = $this->repository->findByName(' MUDKIP ');
        $byAlias = $this->repository->findByName('mizugorou');
        $byExternalReference = $this->repository->findByExternalReference('TCGDEX', ' pokemon:258 ');

        // Assert
        $this->assertInstanceOf(PokemonSpecies::class, $byId);
        foreach ([$byId, $byDex, $bySlug, $byCanonicalName, $byAlias, $byExternalReference] as $match) {
            $this->assertInstanceOf(PokemonSpecies::class, $match);
            $this->assertSame($speciesId, $match->getId()->toRfc4122());
        }
        $this->assertCount(1, $byId->getAliases());
        $this->assertCount(1, $byId->getExternalReferences());
        $externalReference = $byId->getExternalReferences()->first();
        $this->assertInstanceOf(PokemonSpeciesExternalReference::class, $externalReference);
        $this->assertSame('2026-08-04', $externalReference->getSourceVersion());
    }

    #[DataProvider('provideInvalidLookup')]
    public function testInvalidRepositoryLookupsReturnNoSpecies(string $lookup, string $value): void
    {
        // Arrange
        $repository = $this->repository;

        // Act
        $species = match ($lookup) {
            'id' => $repository->get($value),
            'name' => $repository->findByName($value),
            'external' => $repository->findByExternalReference($value, ''),
            default => null,
        };

        // Assert
        $this->assertNull($species);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideInvalidLookup(): iterable
    {
        yield 'invalid UUID' => ['id', 'not-a-uuid'];
        yield 'empty name' => ['name', '   '];
        yield 'invalid external reference' => ['external', 'invalid provider!'];
    }

    public function testDatabasePreventsDuplicateNationalDexNumbers(): void
    {
        // Arrange
        $this->entityManager->persist(new PokemonSpecies(25, 'Pikachu', 1));
        $this->entityManager->persist(new PokemonSpecies(25, 'Another species', 1));
        $exception = null;

        // Act
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(UniqueConstraintViolationException::class, $exception);
    }

    public function testDatabasePreventsDuplicateNormalizedCanonicalNames(): void
    {
        // Arrange
        $this->entityManager->persist(new PokemonSpecies(25, 'Pikachu', 1));
        $this->entityManager->persist(new PokemonSpecies(26, ' PIKACHU ', 1));
        $exception = null;

        // Act
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(UniqueConstraintViolationException::class, $exception);
    }

    public function testDatabasePreventsOneExternalIdentityFromResolvingToMultipleSpecies(): void
    {
        // Arrange
        $pikachu = new PokemonSpecies(25, 'Pikachu', 1);
        $raichu = new PokemonSpecies(26, 'Raichu', 1);
        $pikachu->recordExternalReference('tcgdex', 'pokemon:25');
        $raichu->recordExternalReference('tcgdex', 'pokemon:25');
        $this->entityManager->persist($pikachu);
        $this->entityManager->persist($raichu);
        $exception = null;

        // Act
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(UniqueConstraintViolationException::class, $exception);
    }

    public function testAmbiguousAliasesDoNotResolveToAnArbitrarySpecies(): void
    {
        // Arrange
        $firstSpecies = new PokemonSpecies(25, 'Pikachu', 1);
        $secondSpecies = new PokemonSpecies(172, 'Pichu', 2);
        $firstSpecies->addAlias('Sparky', PokemonSpeciesAliasType::AlternateSpelling, 'manual');
        $secondSpecies->addAlias('Sparky', PokemonSpeciesAliasType::AlternateSpelling, 'manual');
        $this->entityManager->persist($firstSpecies);
        $this->entityManager->persist($secondSpecies);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act
        $match = $this->repository->findByName('sparky');

        // Assert
        $this->assertNull($match);
    }

    public function testDatabaseRejectsInvalidSpeciesIdentityEvenOutsideTheDomainModel(): void
    {
        // Arrange
        $now = '2026-08-04 01:00:00';
        $exception = null;

        // Act
        try {
            $this->entityManager->getConnection()->insert('pokemon_species', [
                'id' => '0198ce60-5f00-7d23-a000-000000000001',
                'national_dex_number' => 0,
                'canonical_name' => 'Invalid species',
                'normalized_name' => 'invalid species',
                'slug' => 'invalid-species',
                'generation' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (DriverException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(DriverException::class, $exception);
    }

    public function testDeletingASpeciesCascadesItsUnmappedIdentityMetadata(): void
    {
        // Arrange
        $species = new PokemonSpecies(258, 'Mudkip', 3);
        $species->addAlias(
            name: 'Mizugorou',
            type: PokemonSpeciesAliasType::Transliteration,
            source: 'tcgdex',
        );
        $species->recordExternalReference('tcgdex', 'pokemon:258');
        $this->repository->save($species);

        // Act
        $this->entityManager->remove($species);
        $this->entityManager->flush();

        // Assert
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pokemon_species_aliases',
        ));
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pokemon_species_external_references',
        ));
    }
}
