<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use DomainException;

final class EmptyCart extends DomainException
{
}
