<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Message\SendPasswordResetEmail;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class PasswordResetApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testKnownAndUnknownEmailAddressesReceiveTheSameSafeResponse(): void
    {
        // Arrange
        $knownEmail = 'reset-known-'.Uuid::v7()->toRfc4122().'@example.com';
        $unknownEmail = 'reset-unknown-'.Uuid::v7()->toRfc4122().'@example.com';
        $this->register($knownEmail);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => $knownEmail]);
        self::assertResponseStatusCodeSame(202);
        $knownResponse = (string) $this->client->getResponse()->getContent();
        $message = $this->lastPasswordResetMessage();
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => $unknownEmail]);
        $unknownResponse = (string) $this->client->getResponse()->getContent();

        // Assert
        self::assertResponseStatusCodeSame(202);
        self::assertSame($knownResponse, $unknownResponse);
        self::assertSame('', $unknownResponse);
        $storedHash = $this->entityManager->getConnection()->fetchOne(
            'SELECT token_hash FROM password_reset_tokens WHERE user_id = (SELECT id FROM users WHERE email = :email)',
            ['email' => $knownEmail],
        );
        self::assertSame(hash('sha256', $message->resetToken), $storedHash);
        self::assertNotSame($message->resetToken, $storedHash);
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM password_reset_tokens',
        ));
    }

    public function testNewRequestReplacesThePreviousResetToken(): void
    {
        // Arrange
        $email = 'reset-replace-'.Uuid::v7()->toRfc4122().'@example.com';
        $this->register($email);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => $email]);
        self::assertResponseStatusCodeSame(202);
        $firstMessage = $this->lastPasswordResetMessage();
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => $email]);
        self::assertResponseStatusCodeSame(202);
        $secondMessage = $this->lastPasswordResetMessage();

        // Assert
        self::assertNotSame($firstMessage->resetToken, $secondMessage->resetToken);
        self::assertSame(hash('sha256', $secondMessage->resetToken), $this->entityManager->getConnection()->fetchOne(
            'SELECT token_hash FROM password_reset_tokens WHERE user_id = (SELECT id FROM users WHERE email = :email)',
            ['email' => $email],
        ));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = (SELECT id FROM users WHERE email = :email)',
            ['email' => $email],
        ));
    }

    public function testMalformedEmailReturnsStructuredValidationError(): void
    {
        // Arrange
        $messagesBefore = count($this->passwordResetMessages());

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => 'not-an-email']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
        self::assertSame($messagesBefore, count($this->passwordResetMessages()));
    }

    private function register(string $email): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => 'A-strong-password-123',
            'passwordConfirmation' => 'A-strong-password-123',
        ]);
        self::assertResponseStatusCodeSame(201);
    }

    private function lastPasswordResetMessage(): SendPasswordResetEmail
    {
        foreach (array_reverse($this->passwordResetMessages()) as $message) {
            return $message;
        }

        throw new LogicException('No password reset message was dispatched.');
    }

    /** @return list<SendPasswordResetEmail> */
    private function passwordResetMessages(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendPasswordResetEmail) {
                $messages[] = $message;
            }
        }

        return $messages;
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
