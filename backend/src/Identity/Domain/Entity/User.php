<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(name: 'password_hash')]
        private string $passwordHash,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->email = mb_strtolower(trim($email));
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        if ($this->email === '') {
            throw new LogicException('A persisted user must have an email address.');
        }

        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof DateTimeImmutable;
    }

    public function verifyEmail(DateTimeImmutable $verifiedAt = new DateTimeImmutable()): void
    {
        if ($this->isEmailVerified()) {
            return;
        }

        $this->emailVerifiedAt = $verifiedAt;
        $this->updatedAt = $verifiedAt;
    }

    public function changePasswordHash(
        string $passwordHash,
        DateTimeImmutable $changedAt = new DateTimeImmutable(),
    ): void {
        if (trim($passwordHash) === '') {
            throw new InvalidArgumentException('A password hash cannot be blank.');
        }

        $this->passwordHash = $passwordHash;
        $this->updatedAt = $changedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function eraseCredentials(): void
    {
    }
}
