<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\ArtistFinder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/artists')]
final readonly class ArtistController
{
    #[Route('/suggestions', methods: ['GET'])]
    public function suggestions(ArtistFinder $finder, string $q = '', int $limit = 10): JsonResponse
    {
        return new JsonResponse(['items' => $finder->suggestions($q, max(1, min($limit, 10)))]);
    }

    #[Route('/slug/{slug}', methods: ['GET'])]
    public function getBySlug(string $slug, ArtistFinder $finder): JsonResponse
    {
        $artist = $finder->getBySlug($slug);
        if ($artist === null) {
            throw new NotFoundHttpException('Artist niet gevonden.');
        }

        return new JsonResponse($artist);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id, ArtistFinder $finder): JsonResponse
    {
        $artist = $finder->get($id);
        if ($artist === null) {
            throw new NotFoundHttpException('Artist niet gevonden.');
        }

        return new JsonResponse($artist);
    }
}
