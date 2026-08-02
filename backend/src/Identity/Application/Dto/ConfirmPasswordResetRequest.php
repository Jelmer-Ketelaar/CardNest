<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConfirmPasswordResetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/', message: 'De herstelcode is ongeldig.')]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 4096)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        public string $password,
        #[Assert\NotBlank]
        #[Assert\EqualTo(propertyPath: 'password', message: 'De wachtwoorden komen niet overeen.')]
        public string $passwordConfirmation,
    ) {
    }
}
