<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\ArtistCollectionResponse;
use App\Catalog\Application\Dto\ListArtistsRequest;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Repository\ArtistRepository;

final readonly class ArtistFinder
{
    public function __construct(private ArtistRepository $artists)
    {
    }

    public function list(ListArtistsRequest $request): ArtistCollectionResponse
    {
        $result = $this->artists->list(
            query: $request->q,
            sort: $request->sort,
            page: $request->page,
            perPage: $request->perPage,
            withCardsOnly: $request->withCardsOnly,
        );
        $counts = $this->artists->cardCounts($result->items);

        return new ArtistCollectionResponse(
            items: array_map(
                fn (Artist $artist): array => $this->summary(
                    $artist,
                    $counts[$artist->getId()->toRfc4122()] ?? 0,
                ),
                $result->items,
            ),
            total: $result->total,
            page: $result->page,
            perPage: $result->perPage,
        );
    }

    /** @return list<array<string, mixed>> */
    public function suggestions(string $query, int $limit): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }

        $artists = $this->artists->search($query, $limit);
        $counts = $this->artists->cardCounts($artists);

        return array_map(
            fn (Artist $artist): array => $this->summary(
                $artist,
                $counts[$artist->getId()->toRfc4122()] ?? 0,
            ),
            $artists,
        );
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $artist = $this->artists->get($id);

        return $artist instanceof Artist ? $this->detail($artist) : null;
    }

    /** @return array<string, mixed>|null */
    public function getBySlug(string $slug): ?array
    {
        $artist = $this->artists->getBySlug($slug);

        return $artist instanceof Artist ? $this->detail($artist) : null;
    }

    /** @return array<string, mixed> */
    private function summary(Artist $artist, ?int $cardCount = null): array
    {
        return [
            'id' => $artist->getId()->toRfc4122(),
            'name' => $artist->getCanonicalName(),
            'slug' => $artist->getSlug(),
            'cardCount' => $cardCount ?? $this->artists->cardCount($artist),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Artist $artist): array
    {
        return [
            ...$this->summary($artist),
            'biography' => $artist->getBiography(),
            'websiteUrl' => $artist->getWebsiteUrl(),
            'profileImage' => $artist->getProfileImageUrl(),
        ];
    }
}
