<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CatalogCoverageApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVisitorReceivesAnHonestNotVerifiedGateWithoutASnapshot(): void
    {
        // Act
        $this->client->request(
            'GET',
            '/api/v1/catalog/coverage?provider=tcgdex&region=international&language=en',
        );

        // Assert
        $this->assertResponseIsSuccessful();
        $response = $this->responseData();
        $this->assertNull($response['id'] ?? null);
        $this->assertSame('not_verified', $response['releaseGate']['status'] ?? null);
        $this->assertSame(['no_verified_snapshot'], $response['releaseGate']['blockingReasons'] ?? null);
        $this->assertSame('not_verified', $response['dimensions']['pokemon_species']['status'] ?? null);
        $this->assertSame('not_verified', $response['dimensions']['ordering']['status'] ?? null);
    }

    public function testCatalogAdministrationRequiresAnAdministrator(): void
    {
        // Arrange
        $userToken = $this->authenticatedUser('catalog-user', false);

        // Act
        $this->client->request('GET', '/api/v1/admin/catalog/discrepancies');

        // Assert
        $this->assertResponseStatusCodeSame(401);

        // Act
        $this->client->request('GET', '/api/v1/admin/catalog/discrepancies', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$userToken,
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanGenerateInspectAndResolveCoverageDiscrepancies(): void
    {
        // Arrange
        $adminToken = $this->authenticatedUser('catalog-admin', true);
        $run = $this->completedRun();
        $this->persistCatalogSet();
        $this->entityManager->persist($run);
        $this->entityManager->flush();
        $payload = $this->coveragePayload($run);

        // Act
        $this->jsonRequest('POST', '/api/v1/admin/catalog/coverage-snapshots', $payload, $adminToken);

        // Assert
        $this->assertResponseIsSuccessful();
        $snapshot = $this->responseData();
        $this->assertSame('blocked_discrepancies', $snapshot['releaseGate']['status'] ?? null);
        $this->assertSame('approved-manifest-2026-08-04', $snapshot['evidence']['sourceVersion'] ?? null);
        $this->assertSame(
            'catalog-evidence://international/en/2026-08-04',
            $snapshot['evidence']['reference'] ?? null,
        );
        $this->assertStringNotContainsString('sourcePayload', json_encode($snapshot, JSON_THROW_ON_ERROR));

        // Act
        $this->client->request(
            'GET',
            '/api/v1/admin/catalog/discrepancies?type=missing_set&severity=critical',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$adminToken],
        );

        // Assert
        $this->assertResponseIsSuccessful();
        $discrepancies = $this->responseData();
        $this->assertSame(1, $discrepancies['total'] ?? null);
        $this->assertSame('missing-set', $discrepancies['items'][0]['externalReference'] ?? null);
        $discrepancyId = $discrepancies['items'][0]['id'] ?? null;
        $this->assertIsString($discrepancyId);

        // Act
        $this->jsonRequest('PATCH', '/api/v1/admin/catalog/discrepancies/'.$discrepancyId, [
            'status' => 'resolved',
            'reason' => 'The approved source manifest was corrected and reviewed.',
        ], $adminToken);

        // Assert
        $this->assertResponseIsSuccessful();
        $resolved = $this->responseData();
        $this->assertSame('resolved', $resolved['status'] ?? null);
        $this->assertSame(1, count($resolved['resolution']['history'] ?? []));

        // Act
        $this->client->request(
            'GET',
            '/api/v1/catalog/coverage?provider=tcgdex&region=international&language=en',
        );

        // Assert
        $this->assertResponseIsSuccessful();
        $publicSnapshot = $this->responseData();
        $this->assertSame($snapshot['id'] ?? null, $publicSnapshot['id'] ?? null);
        $this->assertFalse($publicSnapshot['releaseGate']['passed'] ?? true);
        $this->assertArrayNotHasKey('reference', $publicSnapshot['evidence'] ?? []);
    }

    public function testInvalidCoverageManifestReturnsAValidationError(): void
    {
        // Arrange
        $adminToken = $this->authenticatedUser('invalid-manifest-admin', true);
        $payload = [
            'importRunId' => Uuid::v7()->toRfc4122(),
            'provider' => 'tcgdex',
            'region' => 'international',
            'language' => 'en',
            'evidenceSource' => 'approved-manifest',
            'evidenceVersion' => '2026-08-04',
            'evidenceReference' => 'catalog-evidence://international/en/2026-08-04',
            'dataCutoff' => '2026-08-04T00:00:00+00:00',
            'sets' => [
                ['externalId' => 'base1', 'expectedCardCount' => 1],
                ['externalId' => 'base1', 'expectedCardCount' => 1],
            ],
        ];

        // Act
        $this->jsonRequest('POST', '/api/v1/admin/catalog/coverage-snapshots', $payload, $adminToken);

        // Assert
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    private function authenticatedUser(string $prefix, bool $admin): string
    {
        $user = new User($prefix.'-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $userId = $user->getId()->toRfc4122();
        if ($admin) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE users SET roles = :roles WHERE id = :id',
                ['roles' => json_encode(['ROLE_ADMIN'], JSON_THROW_ON_ERROR), 'id' => $userId],
            );
        }
        $this->entityManager->clear();
        $reloadedUser = $this->entityManager->find(User::class, $userId);
        $this->assertInstanceOf(User::class, $reloadedUser);
        $plainToken = bin2hex(random_bytes(32));
        $this->entityManager->persist(AuthToken::issue($reloadedUser, $plainToken));
        $this->entityManager->flush();

        return $plainToken;
    }

    private function completedRun(): CatalogImportRun
    {
        $run = new CatalogImportRun(
            provider: 'tcgdex',
            scopeKey: hash('sha256', 'unbounded-en'),
            dataScope: ['languages' => ['en'], 'setIds' => []],
            sourceVersion: 'approved-manifest-2026-08-04',
            dataCutoff: new DateTimeImmutable('2026-08-04T00:00:00+00:00'),
        );
        $run->markValidating(hash('sha256', 'base1'), 1, 1);
        $run->markPublishing();
        $run->complete(1, 1);

        return $run;
    }

    private function persistCatalogSet(): void
    {
        $series = new CardSeries('Coverage API series '.Uuid::v7()->toRfc4122());
        $set = new CardSet(
            series: $series,
            name: 'Coverage API set',
            code: 'tcgdex:en:base1',
            language: 'en',
            printedTotal: 1,
            total: 1,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard(
            set: $set,
            name: 'Coverage API card',
            number: '1/1',
            rarity: 'Common',
            cardType: 'Pokemon',
            imageUrl: 'https://assets.example.test/coverage-api-card',
        );
        $this->entityManager->persist($series);
        $this->entityManager->persist($set);
        $this->entityManager->persist($card);
    }

    /** @return array<string, mixed> */
    private function coveragePayload(CatalogImportRun $run): array
    {
        return [
            'importRunId' => $run->getId()->toRfc4122(),
            'provider' => 'tcgdex',
            'region' => 'international',
            'language' => 'en',
            'evidenceSource' => 'approved-manifest',
            'evidenceVersion' => '2026-08-04',
            'evidenceReference' => 'catalog-evidence://international/en/2026-08-04',
            'dataCutoff' => '2026-08-04T00:00:00+00:00',
            'sets' => [
                ['externalId' => 'base1', 'expectedCardCount' => 1],
                ['externalId' => 'missing-set', 'expectedCardCount' => 1],
            ],
            'expectedImageCardCount' => 2,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, string $uri, array $payload, string $token): void
    {
        $this->client->jsonRequest($method, $uri, $payload, [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        return $data;
    }
}
