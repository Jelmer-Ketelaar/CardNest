<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\PokemonForm;
use App\Catalog\Domain\Entity\PokemonSpecies;
use App\Catalog\Domain\Entity\PokemonSpeciesExternalReference;
use App\Catalog\Domain\Repository\PokemonFormRepository;
use App\Catalog\Domain\ValueObject\PokemonFormName;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrinePokemonFormRepository implements PokemonFormRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $id): ?PokemonForm
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        $form = $this->entityManager->find(PokemonForm::class, $uuid);

        return $form instanceof PokemonForm ? $form : null;
    }

    public function findBySpecies(PokemonSpecies $species): array
    {
        /** @var list<PokemonForm> $forms */
        $forms = $this->entityManager->createQueryBuilder()
            ->select('form')
            ->from(PokemonForm::class, 'form')
            ->where('form.species = :species')
            ->setParameter('species', $species)
            ->orderBy('form.isDefault', 'DESC')
            ->addOrderBy('form.normalizedName', 'ASC')
            ->addOrderBy('form.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $forms;
    }

    public function findBySpeciesAndName(PokemonSpecies $species, string $name): ?PokemonForm
    {
        try {
            $normalizedName = PokemonFormName::fromString($name)->getNormalized();
        } catch (InvalidArgumentException) {
            return null;
        }

        $form = $this->entityManager->getRepository(PokemonForm::class)->findOneBy([
            'species' => $species,
            'normalizedName' => $normalizedName,
        ]);

        return $form instanceof PokemonForm ? $form : null;
    }

    public function findBySpeciesAndSlug(PokemonSpecies $species, string $slug): ?PokemonForm
    {
        $form = $this->entityManager->getRepository(PokemonForm::class)->findOneBy([
            'species' => $species,
            'slug' => mb_strtolower(trim($slug)),
        ]);

        return $form instanceof PokemonForm ? $form : null;
    }

    public function findDefaultForSpecies(PokemonSpecies $species): ?PokemonForm
    {
        $form = $this->entityManager->getRepository(PokemonForm::class)->findOneBy([
            'species' => $species,
            'isDefault' => true,
        ]);

        return $form instanceof PokemonForm ? $form : null;
    }

    public function findByExternalReference(string $provider, string $externalId): ?PokemonForm
    {
        try {
            $normalizedProvider = PokemonSpeciesExternalReference::normalizeProvider($provider);
            $normalizedExternalId = PokemonSpeciesExternalReference::normalizeExternalId($externalId);
        } catch (InvalidArgumentException) {
            return null;
        }

        $form = $this->entityManager->createQueryBuilder()
            ->select('form')
            ->from(PokemonForm::class, 'form')
            ->innerJoin('form.externalReferences', 'reference')
            ->where('reference.provider = :provider')
            ->andWhere('reference.externalId = :externalId')
            ->setParameter('provider', $normalizedProvider)
            ->setParameter('externalId', $normalizedExternalId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $form instanceof PokemonForm ? $form : null;
    }
}
