<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Message\SendVerificationEmail;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class EmailVerificationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRegistrationCreatesAHashedSingleUseTokenThatVerifiesEmail(): void
    {
        // Arrange
        $email = 'verify-'.Uuid::v7()->toRfc4122().'@example.com';
        $message = $this->register($email);

        // Act
        $storedHash = $this->entityManager->getConnection()->fetchOne(
            'SELECT token_hash FROM email_verification_tokens WHERE user_id = (SELECT id FROM users WHERE email = :email)',
            ['email' => $email],
        );
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', ['token' => $message->verificationToken]);

        // Assert
        self::assertSame(hash('sha256', $message->verificationToken), $storedHash);
        self::assertNotSame($message->verificationToken, $storedHash);
        self::assertResponseIsSuccessful();
        $user = $this->responseData()['user'] ?? [];
        self::assertSame($email, $user['email'] ?? null);
        self::assertIsString($user['emailVerifiedAt'] ?? null);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM email_verification_tokens WHERE token_hash = :hash',
            ['hash' => $storedHash],
        ));

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', ['token' => $message->verificationToken]);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_email_verification_token', $this->responseData()['error']['code'] ?? null);
    }

    public function testResendInvalidatesThePreviousTokenAndRejectsVerifiedAccounts(): void
    {
        // Arrange
        $email = 'resend-'.Uuid::v7()->toRfc4122().'@example.com';
        $firstMessage = $this->register($email);
        $authToken = $this->login($email);

        // Act
        $this->authorizedRequest('POST', '/api/v1/auth/email-verification/resend', $authToken);

        // Assert
        self::assertResponseStatusCodeSame(202);
        $secondMessage = $this->lastVerificationMessage();
        self::assertNotSame($firstMessage->verificationToken, $secondMessage->verificationToken);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', [
            'token' => $firstMessage->verificationToken,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(400);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', [
            'token' => $secondMessage->verificationToken,
        ]);

        // Assert
        self::assertResponseIsSuccessful();

        // Act
        $this->authorizedRequest('POST', '/api/v1/auth/email-verification/resend', $authToken);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertSame('email_already_verified', $this->responseData()['error']['code'] ?? null);
    }

    public function testVerificationValidationAndResendAuthenticationAreStructured(): void
    {
        // Arrange
        $malformedToken = 'not-a-token';

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', ['token' => $malformedToken]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/email-verification/verify', ['token' => str_repeat('f', 64)]);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_email_verification_token', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->client->request('POST', '/api/v1/auth/email-verification/resend', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_required', $this->responseData()['error']['code'] ?? null);
    }

    private function register(string $email): SendVerificationEmail
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => 'A-strong-password-123',
            'passwordConfirmation' => 'A-strong-password-123',
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->lastVerificationMessage();
    }

    private function login(string $email): string
    {
        $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'A-strong-password-123',
        ]);
        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function lastVerificationMessage(): SendVerificationEmail
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        foreach (array_reverse($transport->getSent()) as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendVerificationEmail) {
                return $message;
            }
        }

        throw new LogicException('No verification message was dispatched.');
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

    private function authorizedRequest(string $method, string $uri, string $token): void
    {
        $this->client->request($method, $uri, server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
