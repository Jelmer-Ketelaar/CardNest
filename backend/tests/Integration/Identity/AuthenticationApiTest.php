<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AuthenticationApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testVisitorCanRegisterLoginAndLogout(): void
    {
        // Arrange
        $email = sprintf('collector-%s@example.com', Uuid::v7()->toRfc4122());
        $password = 'A-strong-password-123';

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
            'passwordConfirmation' => $password,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(201);
        $registration = $this->responseData();
        self::assertSame($email, $registration['user']['email'] ?? null);
        self::assertSame(['ROLE_USER'], $registration['user']['roles'] ?? null);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        $login = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertIsString($login['token'] ?? null);

        // Act
        $this->client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$login['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(204);

        // Act
        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$login['token'],
        ]);

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testInvalidRegistrationReturnsStructuredValidationErrors(): void
    {
        // Arrange
        $payload = ['email' => 'invalid', 'password' => 'short', 'passwordConfirmation' => 'different'];

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/register', $payload);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
    }

    public function testInvalidCredentialsReturnTheSameSafeAuthenticationError(): void
    {
        // Arrange
        $email = sprintf('login-%s@example.com', Uuid::v7()->toRfc4122());
        $password = 'A-strong-password-123';
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
            'passwordConfirmation' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password']);
        $wrongPasswordResponse = $this->responseData()['error'] ?? null;

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertIsArray($wrongPasswordResponse);
        self::assertSame('invalid_credentials', $wrongPasswordResponse['code'] ?? null);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => sprintf('unknown-%s@example.com', Uuid::v7()->toRfc4122()),
            'password' => 'wrong-password',
        ]);
        $unknownEmailResponse = $this->responseData()['error'] ?? null;

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame($wrongPasswordResponse, $unknownEmailResponse);
    }

    public function testProtectedEndpointReturnsStructuredAuthenticationError(): void
    {
        // Arrange
        $headers = ['HTTP_ACCEPT' => 'application/json'];

        // Act
        $this->client->request('GET', '/api/v1/me', server: $headers);

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);
    }

    public function testLogoutRequiresAuthentication(): void
    {
        // Arrange
        $headers = ['HTTP_ACCEPT' => 'application/json'];

        // Act
        $this->client->request('POST', '/api/v1/auth/logout', server: $headers);

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);
    }

    public function testAuthenticatedUserCanRetrieveTheirSafeProfile(): void
    {
        // Arrange
        $email = sprintf('current-user-%s@example.com', Uuid::v7()->toRfc4122());
        $password = 'A-strong-password-123';
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
            'passwordConfirmation' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);
        $registeredUser = $this->responseData()['user'] ?? null;
        self::assertIsArray($registeredUser);
        self::assertIsString($registeredUser['id'] ?? null);

        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'] ?? null;
        self::assertIsString($token);

        // Act
        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        // Assert
        self::assertResponseIsSuccessful();
        $user = $this->responseData()['user'] ?? null;
        self::assertIsArray($user);
        self::assertSame($registeredUser['id'], $user['id'] ?? null);
        self::assertSame($email, $user['email'] ?? null);
        self::assertSame(['ROLE_USER'], $user['roles'] ?? null);
        self::assertArrayHasKey('emailVerifiedAt', $user);
        self::assertNull($user['emailVerifiedAt']);
        self::assertIsString($user['createdAt'] ?? null);
        self::assertArrayNotHasKey('password', $user);
        self::assertArrayNotHasKey('passwordHash', $user);
        self::assertArrayNotHasKey('token', $user);
    }

    public function testAuthenticatedUserCanOnlyRetrieveTheirOwnProfile(): void
    {
        // Arrange
        $firstEmail = sprintf('first-user-%s@example.com', Uuid::v7()->toRfc4122());
        $secondEmail = sprintf('second-user-%s@example.com', Uuid::v7()->toRfc4122());
        $password = 'A-strong-password-123';
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $firstEmail,
            'password' => $password,
            'passwordConfirmation' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);
        $firstUser = $this->responseData()['user'] ?? null;
        self::assertIsArray($firstUser);
        self::assertIsString($firstUser['id'] ?? null);

        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $secondEmail,
            'password' => $password,
            'passwordConfirmation' => $password,
        ]);
        self::assertResponseStatusCodeSame(201);
        $secondUser = $this->responseData()['user'] ?? null;
        self::assertIsArray($secondUser);
        self::assertIsString($secondUser['id'] ?? null);

        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $secondEmail, 'password' => $password]);
        self::assertResponseIsSuccessful();
        $secondToken = $this->responseData()['token'] ?? null;
        self::assertIsString($secondToken);

        // Act
        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$secondToken,
        ]);

        // Assert
        self::assertResponseIsSuccessful();
        $profile = $this->responseData()['user'] ?? null;
        self::assertIsArray($profile);
        self::assertSame($secondUser['id'], $profile['id'] ?? null);
        self::assertSame($secondEmail, $profile['email'] ?? null);
        self::assertNotSame($firstUser['id'], $profile['id']);
        self::assertNotSame($firstEmail, $profile['email']);
    }

    /** @param array<string, string> $payload */
    private function jsonRequest(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
