<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthApiTest extends WebTestCase
{
    public function testHealthEndpointIsPublic(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/api/v1/health', server: ['HTTP_ACCEPT' => 'application/json']);

        // Assert
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'ok', 'service' => 'cardnest-backend'], $data);
    }
}
