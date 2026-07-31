<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use DomainException;

final class EmailAlreadyRegistered extends DomainException
{
}
