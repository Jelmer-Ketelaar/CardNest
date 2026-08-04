<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Application\Exception\InvalidCatalogCoverageEvidence;
use App\Catalog\Domain\Entity\CatalogCoverageDimension;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifestSet;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use ValueError;

#[Assert\Callback('validateManifest')]
final readonly class GenerateCatalogCoverageRequest
{
    /**
     * @param list<mixed> $sets
     * @param list<mixed> $blockedDimensions
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $importRunId,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $provider,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 50)]
        public string $region,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 10)]
        public string $language,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $evidenceSource,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 180)]
        public string $evidenceVersion,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 1_024)]
        public string $evidenceReference,
        #[Assert\NotBlank]
        #[Assert\DateTime(format: DATE_ATOM)]
        public string $dataCutoff,
        #[Assert\Count(min: 1, max: 5_000)]
        public array $sets,
        #[Assert\PositiveOrZero]
        public ?int $expectedPokemonSpeciesCount = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedPokemonFormCount = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedImageCardCount = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedArtistCardCount = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedPokemonMappedCardCount = null,
        #[Assert\PositiveOrZero]
        public ?int $expectedPricedCardCount = null,
        public array $blockedDimensions = [],
    ) {
    }

    public function toManifest(): CatalogCoverageManifest
    {
        try {
            return $this->createManifest();
        } catch (InvalidArgumentException|ValueError $exception) {
            throw new InvalidCatalogCoverageEvidence($exception->getMessage(), previous: $exception);
        }
    }

    private function createManifest(): CatalogCoverageManifest
    {
        $sets = [];
        foreach ($this->sets as $index => $set) {
            if (!is_array($set)) {
                throw new InvalidCatalogCoverageEvidence(sprintf('Coverage set at index %d is invalid.', $index));
            }
            $externalId = $set['externalId'] ?? null;
            $expectedCardCount = $set['expectedCardCount'] ?? null;
            if (!is_string($externalId) || !is_int($expectedCardCount)) {
                throw new InvalidCatalogCoverageEvidence(sprintf('Coverage set at index %d is invalid.', $index));
            }
            $sets[] = new CatalogCoverageManifestSet($externalId, $expectedCardCount);
        }

        $blockedDimensions = [];
        foreach ($this->blockedDimensions as $index => $dimension) {
            if (!is_string($dimension)) {
                throw new InvalidCatalogCoverageEvidence(sprintf('Blocked coverage dimension at index %d is invalid.', $index));
            }
            $blockedDimensions[] = CatalogCoverageDimension::from($dimension);
        }
        $dataCutoff = DateTimeImmutable::createFromFormat(DATE_ATOM, $this->dataCutoff);
        if (!$dataCutoff instanceof DateTimeImmutable) {
            throw new InvalidCatalogCoverageEvidence('Coverage data cutoff must use ISO-8601.');
        }

        return new CatalogCoverageManifest(
            provider: $this->provider,
            region: $this->region,
            language: $this->language,
            evidenceSource: $this->evidenceSource,
            evidenceVersion: $this->evidenceVersion,
            evidenceReference: $this->evidenceReference,
            dataCutoff: $dataCutoff,
            sets: $sets,
            expectedPokemonSpeciesCount: $this->expectedPokemonSpeciesCount,
            expectedPokemonFormCount: $this->expectedPokemonFormCount,
            expectedImageCardCount: $this->expectedImageCardCount,
            expectedArtistCardCount: $this->expectedArtistCardCount,
            expectedPokemonMappedCardCount: $this->expectedPokemonMappedCardCount,
            expectedPricedCardCount: $this->expectedPricedCardCount,
            blockedDimensions: $blockedDimensions,
        );
    }

    public function validateManifest(ExecutionContextInterface $context): void
    {
        $allowedDimensions = array_map(
            static fn (CatalogCoverageDimension $dimension): string => $dimension->value,
            CatalogCoverageDimension::cases(),
        );
        if (count(array_unique($this->blockedDimensions)) !== count($this->blockedDimensions)) {
            $context->buildViolation('Blocked coverage dimensions must be unique.')
                ->atPath('blockedDimensions')
                ->addViolation();
        }
        foreach ($this->blockedDimensions as $index => $dimension) {
            if (!is_string($dimension) || !in_array($dimension, $allowedDimensions, true)) {
                $context->buildViolation('Unknown blocked coverage dimension.')
                    ->atPath(sprintf('blockedDimensions[%d]', $index))
                    ->addViolation();
            }
        }

        $seenSetIds = [];
        foreach ($this->sets as $index => $set) {
            if (!is_array($set)) {
                $context->buildViolation('Each coverage set must be an object.')
                    ->atPath(sprintf('sets[%d]', $index))
                    ->addViolation();
                continue;
            }
            if (array_diff(array_keys($set), ['externalId', 'expectedCardCount']) !== []) {
                $context->buildViolation('Coverage sets may only contain externalId and expectedCardCount.')
                    ->atPath(sprintf('sets[%d]', $index))
                    ->addViolation();
            }

            $externalId = $set['externalId'] ?? null;
            if (
                !is_string($externalId)
                || $externalId !== trim($externalId)
                || $externalId === ''
                || mb_strlen($externalId) > 180
            ) {
                $context->buildViolation('A set externalId must contain 1 to 180 trimmed characters.')
                    ->atPath(sprintf('sets[%d].externalId', $index))
                    ->addViolation();
            } elseif (isset($seenSetIds[$externalId])) {
                $context->buildViolation('Coverage set externalIds must be unique.')
                    ->atPath(sprintf('sets[%d].externalId', $index))
                    ->addViolation();
            } else {
                $seenSetIds[$externalId] = true;
            }

            $expectedCardCount = $set['expectedCardCount'] ?? null;
            if (!is_int($expectedCardCount) || $expectedCardCount < 0) {
                $context->buildViolation('A set expectedCardCount must be a non-negative integer.')
                    ->atPath(sprintf('sets[%d].expectedCardCount', $index))
                    ->addViolation();
            }
        }
    }
}
