<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function lockById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;
}
