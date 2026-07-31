<?php

declare(strict_types=1);

namespace App\Review\Domain\Exception;

use DomainException;

final class ReviewAlreadyExists extends DomainException
{
}
