<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyEmailRequest
{
    public function __construct(
        #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/', message: 'De verificatietoken is ongeldig.')]
        public string $token,
    ) {
    }
}
