<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An official, released (or announced) product set, e.g. "McDonald's Collection 2021".
 * A set is language-specific: the same cards released in another language form a
 * different CardSet record.
 */
#[ORM\Entity]
#[ORM\Table(name: 'card_sets')]
#[ORM\UniqueConstraint(name: 'uniq_card_sets_code', columns: ['code'])]
final class CardSet
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CardSeries::class)]
        #[ORM\JoinColumn(name: 'series_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly CardSeries $series,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(length: 50)]
        private readonly string $code,
        #[ORM\Column(length: 10)]
        private readonly string $language,
        #[ORM\Column(name: 'printed_total')]
        private int $printedTotal,
        #[ORM\Column]
        private int $total,
        #[ORM\Column(length: 20, enumType: CardSetStatus::class)]
        private CardSetStatus $status,
        #[ORM\Column(name: 'release_date', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?DateTimeImmutable $releaseDate = null,
        #[ORM\Column(name: 'logo_url', length: 1024, nullable: true)]
        private ?string $logoUrl = null,
        #[ORM\Column(name: 'symbol_url', length: 1024, nullable: true)]
        private ?string $symbolUrl = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $description = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSeries(): CardSeries
    {
        return $this->series;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getPrintedTotal(): int
    {
        return $this->printedTotal;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getStatus(): CardSetStatus
    {
        return $this->status;
    }

    public function getReleaseDate(): ?DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function getSymbolUrl(): ?string
    {
        return $this->symbolUrl;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Refreshes provider-owned set metadata without touching CardNest's manual
     * description or the stable source identity (series, code and language).
     */
    public function synchronizeFromProvider(
        string $name,
        int $printedTotal,
        int $total,
        CardSetStatus $status,
        ?DateTimeImmutable $releaseDate,
        ?string $logoUrl,
        ?string $symbolUrl,
        DateTimeImmutable $syncedAt = new DateTimeImmutable(),
    ): void {
        $changed = $this->name !== $name
            || $this->printedTotal !== $printedTotal
            || $this->total !== $total
            || $this->status !== $status
            || $this->releaseDate?->format('Y-m-d') !== $releaseDate?->format('Y-m-d')
            || $this->logoUrl !== $logoUrl
            || $this->symbolUrl !== $symbolUrl;

        if (!$changed) {
            return;
        }

        $this->name = $name;
        $this->printedTotal = $printedTotal;
        $this->total = $total;
        $this->status = $status;
        $this->releaseDate = $releaseDate;
        $this->logoUrl = $logoUrl;
        $this->symbolUrl = $symbolUrl;
        $this->updatedAt = $syncedAt;
    }
}
