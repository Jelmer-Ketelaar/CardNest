<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\PokemonForm;
use App\Catalog\Domain\Entity\PokemonFormExternalReference;
use App\Catalog\Domain\Entity\PokemonFormType;
use App\Catalog\Domain\Entity\PokemonSpecies;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PokemonFormTest extends TestCase
{
    public function testSpeciesCreatesOfficialFormsAndKeepsOneDefault(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2026-08-04T02:00:00+00:00');
        $megaCreatedAt = $createdAt->modify('+1 hour');
        $species = new PokemonSpecies(6, 'Charizard', 1, createdAt: $createdAt);

        // Act
        $defaultForm = $species->addForm('Charizard', PokemonFormType::Default, true, $createdAt);
        $megaForm = $species->addForm(
            'Mega Charizard X',
            PokemonFormType::MegaEvolution,
            false,
            $megaCreatedAt,
        );

        // Assert
        $this->assertCount(2, $species->getForms());
        $this->assertSame($species, $defaultForm->getSpecies());
        $this->assertTrue($defaultForm->isDefault());
        $this->assertSame(PokemonFormType::Default, $defaultForm->getType());
        $this->assertSame('mega-charizard-x', $megaForm->getSlug());
        $this->assertFalse($megaForm->isDefault());
        $this->assertSame($megaCreatedAt, $species->getUpdatedAt());
    }

    public function testSpeciesAddsTheSameFormIdempotently(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);

        // Act
        $firstForm = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $secondForm = $species->addForm('  MEGA   CHARIZARD X ', PokemonFormType::MegaEvolution, false);

        // Assert
        $this->assertSame($firstForm, $secondForm);
        $this->assertCount(1, $species->getForms());
    }

    public function testSpeciesRejectsConflictingDataForTheSameFormName(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $exception = null;

        // Act
        try {
            $species->addForm('Mega Charizard X', PokemonFormType::OfficialAlternate, false);
        } catch (LogicException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertStringContainsString('conflicting identity', $exception->getMessage());
        $this->assertCount(1, $species->getForms());
    }

    public function testSpeciesRejectsASecondDefaultForm(): void
    {
        // Arrange
        $species = new PokemonSpecies(479, 'Rotom', 4);
        $species->addForm('Rotom', PokemonFormType::Default, true);
        $exception = null;

        // Act
        try {
            $species->addForm('Standard Rotom', PokemonFormType::Default, true);
        } catch (LogicException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertStringContainsString('only one default', $exception->getMessage());
        $this->assertCount(1, $species->getForms());
    }

    public function testSpeciesRejectsDifferentNamesThatProduceTheSameSlug(): void
    {
        // Arrange
        $species = new PokemonSpecies(669, 'Flabébé', 6);
        $species->addForm('Flabébé Flower', PokemonFormType::OfficialAlternate, false);
        $exception = null;

        // Act
        try {
            $species->addForm('Flabebe Flower', PokemonFormType::OfficialAlternate, false);
        } catch (LogicException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertStringContainsString('slug must be unique', $exception->getMessage());
        $this->assertCount(1, $species->getForms());
    }

    #[DataProvider('provideContradictingDefaultIdentity')]
    public function testFormRejectsContradictingTypeAndDefaultFlag(PokemonFormType $type, bool $isDefault): void
    {
        // Arrange
        $species = new PokemonSpecies(25, 'Pikachu', 1);
        $exception = null;

        // Act
        try {
            new PokemonForm($species, 'Pikachu', $type, $isDefault);
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('marked as default', $exception->getMessage());
    }

    /** @return iterable<string, array{PokemonFormType, bool}> */
    public static function provideContradictingDefaultIdentity(): iterable
    {
        yield 'default type without flag' => [PokemonFormType::Default, false];
        yield 'alternate type with flag' => [PokemonFormType::OfficialAlternate, true];
    }

    public function testItRecordsExternalReferencesIdempotentlyAndAdvancesTheirVersion(): void
    {
        // Arrange
        $firstSync = new DateTimeImmutable('2026-08-04T02:00:00+00:00');
        $secondSync = new DateTimeImmutable('2026-08-04T03:00:00+00:00');
        $species = new PokemonSpecies(6, 'Charizard', 1, createdAt: $firstSync);
        $form = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false, $firstSync);

        // Act
        $firstReference = $form->recordExternalReference('TCGDEX', ' form:charizard-mega-x ', 'v1', $firstSync);
        $secondReference = $form->recordExternalReference('tcgdex', 'form:charizard-mega-x', 'v2', $secondSync);

        // Assert
        $this->assertSame($firstReference, $secondReference);
        $this->assertCount(1, $form->getExternalReferences());
        $this->assertSame('tcgdex', $firstReference->getProvider());
        $this->assertSame('form:charizard-mega-x', $firstReference->getExternalId());
        $this->assertSame('v2', $firstReference->getSourceVersion());
        $this->assertSame($firstSync, $firstReference->getImportedAt());
        $this->assertSame($secondSync, $firstReference->getLastSyncedAt());
        $this->assertSame($secondSync, $form->getUpdatedAt());
    }

    public function testExternalReferenceNormalizesItsIdentityWhenConstructedDirectly(): void
    {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $form = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);

        // Act
        $reference = new PokemonFormExternalReference($form, ' TCGDEX ', ' form:charizard-mega-x ');

        // Assert
        $this->assertSame('tcgdex', $reference->getProvider());
        $this->assertSame('form:charizard-mega-x', $reference->getExternalId());
    }

    #[DataProvider('provideInvalidExternalReferenceIdentity')]
    public function testExternalReferenceRejectsInvalidBoundedIdentity(
        string $provider,
        string $externalId,
        ?string $sourceVersion,
        string $expectedMessage,
    ): void {
        // Arrange
        $species = new PokemonSpecies(6, 'Charizard', 1);
        $form = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false);
        $exception = null;

        // Act
        try {
            new PokemonFormExternalReference($form, $provider, $externalId, $sourceVersion);
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<string, array{string, string, ?string, string}> */
    public static function provideInvalidExternalReferenceIdentity(): iterable
    {
        yield 'invalid provider' => ['invalid provider!', 'form:charizard-mega-x', null, 'provider'];
        yield 'empty external ID' => ['tcgdex', '   ', null, 'external ID'];
        yield 'oversized source version' => ['tcgdex', 'form:charizard-mega-x', str_repeat('v', 181), 'source version'];
    }

    public function testExternalReferenceRejectsAnOutOfOrderSync(): void
    {
        // Arrange
        $importedAt = new DateTimeImmutable('2026-08-04T02:00:00+00:00');
        $species = new PokemonSpecies(6, 'Charizard', 1, createdAt: $importedAt);
        $form = $species->addForm('Mega Charizard X', PokemonFormType::MegaEvolution, false, $importedAt);
        $reference = $form->recordExternalReference('tcgdex', 'form:charizard-mega-x', 'v1', $importedAt);
        $reference->markSynced('v3', new DateTimeImmutable('2026-08-04T04:00:00+00:00'));
        $exception = null;

        // Act
        try {
            $reference->markSynced('v2', new DateTimeImmutable('2026-08-04T03:00:00+00:00'));
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('latest synchronization', $exception->getMessage());
        $this->assertSame('v3', $reference->getSourceVersion());
    }
}
