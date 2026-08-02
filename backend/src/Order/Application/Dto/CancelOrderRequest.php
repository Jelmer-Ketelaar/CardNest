<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancelOrderRequest
{
    public function __construct(
        #[Assert\NotBlank(
            normalizer: 'trim',
            message: 'Een annuleringsreden is verplicht.',
        )]
        #[Assert\Length(
            min: 3,
            max: 500,
            normalizer: 'trim',
            minMessage: 'Een annuleringsreden moet minimaal {{ limit }} tekens bevatten.',
            maxMessage: 'Een annuleringsreden mag maximaal {{ limit }} tekens bevatten.',
        )]
        public string $reason,
    ) {
    }
}
