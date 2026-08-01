<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'email_verification_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_email_verification_user', columns: ['user_id'])]
final class EmailVerificationToken
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private readonly Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly User $user,
        string $plainToken,
        #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $plainToken) !== 1) {
            throw new InvalidArgumentException('An email verification token must contain 64 lowercase hexadecimal characters.');
        }
        if ($expiresAt <= $createdAt) {
            throw new InvalidArgumentException('An email verification token must expire after it is created.');
        }

        $this->id = Uuid::v7();
        $this->tokenHash = self::hash($plainToken);
    }

    #[ORM\Column(name: 'token_hash', length: 64, unique: true)]
    private readonly string $tokenHash;

    public static function issue(
        User $user,
        string $plainToken,
        DateTimeImmutable $issuedAt = new DateTimeImmutable(),
    ): self {
        return new self($user, $plainToken, $issuedAt->modify('+24 hours'), $issuedAt);
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(DateTimeImmutable $now = new DateTimeImmutable()): bool
    {
        return $this->expiresAt <= $now;
    }
}
