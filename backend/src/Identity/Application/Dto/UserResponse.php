<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

use App\Identity\Domain\Entity\User;

final readonly class UserResponse
{
    public function __construct(private User $user)
    {
    }

    /** @return array{id: string, email: string, roles: list<string>, emailVerifiedAt: ?string, createdAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->user->getId()->toRfc4122(),
            'email' => $this->user->getEmail(),
            'roles' => $this->user->getRoles(),
            'emailVerifiedAt' => $this->user->getEmailVerifiedAt()?->format(DATE_ATOM),
            'createdAt' => $this->user->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
