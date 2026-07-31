<?php

declare(strict_types=1);

namespace App\Shared\Domain\Transaction;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;
}
