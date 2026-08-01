<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use RuntimeException;

final class InvalidEmailVerificationToken extends RuntimeException
{
}
