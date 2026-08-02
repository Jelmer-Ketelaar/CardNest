<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'artists')]
#[ORM\UniqueConstraint(name: 'uniq_artists_normalized_name', columns: ['normalized_name'])]
#[ORM\UniqueConstraint(name: 'uniq_artists_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_artists_normalized_name', columns: ['normalized_name'])]
final class Artist
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, CatalogCardArtist> */
    #[ORM\OneToMany(mappedBy: 'artist', targetEntity: CatalogCardArtist::class)]
    private Collection $cardLinks;

    /** @param array<string, mixed> $externalReferences */
    public function __construct(
        #[ORM\Column(name: 'canonical_name', length: 180)]
        private readonly string $canonicalName,
        #[ORM\Column(name: 'normalized_name', length: 180)]
        private readonly string $normalizedName,
        #[ORM\Column(length: 220)]
        private readonly string $slug,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $biography = null,
        #[ORM\Column(name: 'website_url', length: 1024, nullable: true)]
        private ?string $websiteUrl = null,
        #[ORM\Column(name: 'profile_image_url', length: 1024, nullable: true)]
        private ?string $profileImageUrl = null,
        #[ORM\Column(name: 'external_references', type: Types::JSONB)]
        private array $externalReferences = [],
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->cardLinks = new ArrayCollection();
        $this->updatedAt = $createdAt;
    }

    public static function fromCanonicalName(string $name): self
    {
        $canonicalName = self::canonicalizeName($name);
        $normalizedName = self::normalizeName($canonicalName);
        $slug = (new AsciiSlugger())->slug($canonicalName)->lower()->toString();

        return new self($canonicalName, $normalizedName, $slug !== '' ? $slug : 'artist');
    }

    public static function canonicalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(self::canonicalizeName($name));
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function getProfileImageUrl(): ?string
    {
        return $this->profileImageUrl;
    }

    /** @return array<string, mixed> */
    public function getExternalReferences(): array
    {
        return $this->externalReferences;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
