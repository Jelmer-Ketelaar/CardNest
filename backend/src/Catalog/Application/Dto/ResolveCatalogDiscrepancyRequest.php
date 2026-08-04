<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResolveCatalogDiscrepancyRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['accepted', 'resolved', 'ignored_with_reason', 'blocked_external'])]
        public string $status,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(min: 10, max: 1_000, normalizer: 'trim')]
        #[Assert\Regex(pattern: '/[\x00-\x1F\x7F]/u', match: false)]
        public string $reason,
    ) {
    }
}
