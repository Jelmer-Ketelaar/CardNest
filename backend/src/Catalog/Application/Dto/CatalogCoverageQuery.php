<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CatalogCoverageQuery
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $provider = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 50)]
        public string $region = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 10)]
        public string $language = '',
    ) {
    }
}
