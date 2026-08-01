<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Message\SendPasswordResetEmail;
use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use DateTimeImmutable;
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
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->users = self::getContainer()->get(UserRepository::class);
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

    public function testValidTokenUpdatesThePasswordOnceAndRevokesExistingSessions(): void
    {
        // Arrange
        $email = 'reset-confirm-'.Uuid::v7()->toRfc4122().'@example.com';
        $oldPassword = 'A-strong-password-123';
        $newPassword = 'A-new-strong-password-456';
        $this->register($email);
        $oldAccessToken = $this->login($email, $oldPassword);
        $this->jsonRequest('POST', '/api/v1/auth/forgot-password', ['email' => $email]);
        self::assertResponseStatusCodeSame(202);
        $message = $this->lastPasswordResetMessage();

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/reset-password', [
            'token' => $message->resetToken,
            'password' => $newPassword,
            'passwordConfirmation' => $newPassword,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM password_reset_tokens',
        ));
        $storedHash = $this->entityManager->getConnection()->fetchOne(
            'SELECT password_hash FROM users WHERE email = :email',
            ['email' => $email],
        );
        self::assertIsString($storedHash);
        self::assertNotSame($newPassword, $storedHash);

        // Act
        $this->client->request('GET', '/api/v1/me', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$oldAccessToken,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(401);
        self::assertSame('authentication_failed', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $oldPassword]);

        // Assert
        self::assertResponseStatusCodeSame(401);

        // Act
        $newAccessToken = $this->login($email, $newPassword);
        $this->jsonRequest('POST', '/api/v1/auth/reset-password', [
            'token' => $message->resetToken,
            'password' => $newPassword,
            'passwordConfirmation' => $newPassword,
        ]);

        // Assert
        self::assertNotSame($oldAccessToken, $newAccessToken);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_password_reset_token', $this->responseData()['error']['code'] ?? null);
    }

    public function testInvalidAndExpiredTokensFailWithoutChangingThePassword(): void
    {
        // Arrange
        $email = 'reset-expired-'.Uuid::v7()->toRfc4122().'@example.com';
        $password = 'A-strong-password-123';
        $newPassword = 'A-new-strong-password-456';
        $this->register($email);
        $createdAt = new DateTimeImmutable('-2 hours');
        $expiredToken = bin2hex(random_bytes(32));
        $user = $this->users->findByEmail($email);
        self::assertInstanceOf(User::class, $user);
        $this->entityManager->persist(new PasswordResetToken(
            $user,
            $expiredToken,
            $createdAt->modify('+1 hour'),
            $createdAt,
        ));
        $this->entityManager->flush();

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/reset-password', [
            'token' => str_repeat('f', 64),
            'password' => $newPassword,
            'passwordConfirmation' => $newPassword,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_password_reset_token', $this->responseData()['error']['code'] ?? null);

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/reset-password', [
            'token' => $expiredToken,
            'password' => $newPassword,
            'passwordConfirmation' => $newPassword,
        ]);

        // Assert
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_password_reset_token', $this->responseData()['error']['code'] ?? null);

        // Act
        $accessToken = $this->login($email, $password);

        // Assert
        self::assertNotSame('', $accessToken);
    }

    public function testResetPasswordValidationErrorsAreStructured(): void
    {
        // Arrange
        $payload = [
            'token' => 'invalid',
            'password' => 'short',
            'passwordConfirmation' => 'different',
        ];

        // Act
        $this->jsonRequest('POST', '/api/v1/auth/reset-password', $payload);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->responseData()['error']['code'] ?? null);
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

    private function login(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();
        $token = $this->responseData()['token'] ?? null;
        self::assertIsString($token);

        return $token;
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
