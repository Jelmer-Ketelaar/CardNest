<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\PokemonForm;
use App\Catalog\Domain\Entity\PokemonFormExternalReference;
use App\Catalog\Domain\Entity\PokemonFormType;
use App\Catalog\Domain\Entity\PokemonSpecies;
use App\Catalog\Domain\Repository\PokemonFormRepository;
use App\Catalog\Domain\Repository\PokemonSpeciesRepository;
use App\Catalog\Infrastructure\Persistence\DoctrinePokemonFormRepository;
use App\Catalog\Infrastructure\Persistence\DoctrinePokemonSpeciesRepository;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class PokemonFormPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PokemonFormRepository $forms;
    private PokemonSpeciesRepository $species;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->forms = new DoctrinePokemonFormRepository($this->entityManager);
        $this->species = new DoctrinePokemonSpeciesRepository($this->entityManager);
    }

    public function testItPersistsAndFindsFormsByEverySupportedIdentity(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $defaultForm = $species->addForm('Charizard', PokemonFormType::Default, true);
        $megaForm = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $megaForm->recordExternalReference('tcgdex', 'form:charizard-mega-x', '2026-08-04');
        $this->entityManager->persist($species);

        // Act
        $this->entityManager->flush();
        $speciesId = $species->getId()->toRfc4122();
        $defaultFormId = $defaultForm->getId()->toRfc4122();
        $megaFormId = $megaForm->getId()->toRfc4122();
        $this->entityManager->clear();
        $loadedSpecies = $this->species->get($speciesId);
        $byId = $this->forms->get($megaFormId);
        $bySpecies = $loadedSpecies instanceof PokemonSpecies ? $this->forms->findBySpecies($loadedSpecies) : [];
        $byName = $loadedSpecies instanceof PokemonSpecies
            ? $this->forms->findBySpeciesAndName($loadedSpecies, ' MEGA CHARIZARD X ')
            : null;
        $bySlug = $loadedSpecies instanceof PokemonSpecies
            ? $this->forms->findBySpeciesAndSlug($loadedSpecies, 'MEGA-CHARIZARD-X')
            : null;
        $default = $loadedSpecies instanceof PokemonSpecies
            ? $this->forms->findDefaultForSpecies($loadedSpecies)
            : null;
        $byExternalReference = $this->forms->findByExternalReference(
            'TCGDEX',
            ' form:charizard-mega-x ',
        );

        // Assert
        $this->assertInstanceOf(PokemonSpecies::class, $loadedSpecies);
        $this->assertInstanceOf(PokemonForm::class, $byId);
        $this->assertCount(2, $bySpecies);
        $this->assertSame($defaultFormId, $bySpecies[0]->getId()->toRfc4122());
        foreach ([$byId, $byName, $bySlug, $byExternalReference] as $match) {
            $this->assertInstanceOf(PokemonForm::class, $match);
            $this->assertSame($megaFormId, $match->getId()->toRfc4122());
        }
        $this->assertInstanceOf(PokemonForm::class, $default);
        $this->assertSame($defaultFormId, $default->getId()->toRfc4122());
        $this->assertCount(1, $byId->getExternalReferences());
        $externalReference = $byId->getExternalReferences()->first();
        $this->assertInstanceOf(PokemonFormExternalReference::class, $externalReference);
        $this->assertSame('2026-08-04', $externalReference->getSourceVersion());
    }

    #[DataProvider('provideInvalidLookup')]
    public function testInvalidRepositoryLookupsReturnNoForm(string $lookup, string $value): void
    {
        // Arrange
        $species = new PokemonSpecies(25, 'Pikachu', 1);

        // Act
        $form = match ($lookup) {
            'id' => $this->forms->get($value),
            'name' => $this->forms->findBySpeciesAndName($species, $value),
            'external' => $this->forms->findByExternalReference($value, ''),
            default => null,
        };

        // Assert
        $this->assertNull($form);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideInvalidLookup(): iterable
    {
        yield 'invalid UUID' => ['id', 'not-a-uuid'];
        yield 'card mechanic name' => ['name', 'Pikachu VMAX'];
        yield 'invalid external reference' => ['external', 'invalid provider!'];
    }

    public function testDatabasePreventsTwoDefaultFormsForOneSpecies(): void
    {
        // Arrange
        $species = new PokemonSpecies(479, 'Rotom', 4);
        $this->entityManager->persist($species);
        $this->entityManager->persist(new PokemonForm($species, 'Rotom', PokemonFormType::Default, true));
        $this->entityManager->persist(new PokemonForm($species, 'Standard Rotom', PokemonFormType::Default, true));
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

    public function testDatabasePreventsDuplicateNormalizedFormNamesForOneSpecies(): void
    {
        // Arrange
        $species = new PokemonSpecies(386, 'Deoxys', 3);
        $this->entityManager->persist($species);
        $this->entityManager->persist(
            new PokemonForm($species, 'Attack Forme', PokemonFormType::OfficialAlternate, false),
        );
        $this->entityManager->persist(
            new PokemonForm($species, ' ATTACK FORME ', PokemonFormType::OfficialAlternate, false),
        );
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

    public function testDatabasePreventsOneExternalIdentityFromResolvingToMultipleForms(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $firstForm = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $secondForm = $species->addForm('Mega Charizard Y', PokemonFormType::MegaEvolution, false);
        $firstForm->recordExternalReference('tcgdex', 'form:charizard-mega');
        $secondForm->recordExternalReference('tcgdex', 'form:charizard-mega');
        $this->entityManager->persist($species);
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

    public function testDatabaseRejectsAContradictingDefaultTypeOutsideTheDomainModel(): void
    {
        // Arrange
        $species = new PokemonSpecies(25, 'Pikachu', 1);
        $this->entityManager->persist($species);
        $this->entityManager->flush();
        $now = '2026-08-04 02:00:00';
        $exception = null;

        // Act
        try {
            $this->entityManager->getConnection()->insert('pokemon_forms', [
                'id' => Uuid::v7()->toRfc4122(),
                'species_id' => $species->getId()->toRfc4122(),
                'canonical_name' => 'Pikachu',
                'normalized_name' => 'pikachu',
                'slug' => 'pikachu',
                'form_type' => 'regional',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (DriverException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(DriverException::class, $exception);
    }

    public function testDeletingASpeciesCascadesFormsAndTheirSourceReferences(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $form = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $form->recordExternalReference('tcgdex', 'form:charizard-mega-x');
        $this->entityManager->persist($species);
        $this->entityManager->flush();

        // Act
        $this->entityManager->remove($species);
        $this->entityManager->flush();

        // Assert
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pokemon_forms',
        ));
        $this->assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pokemon_form_external_references',
        ));
    }
}
