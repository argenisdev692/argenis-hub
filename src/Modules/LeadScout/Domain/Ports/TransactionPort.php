<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

/**
 * Atomic unit of work for use-cases that write more than one row. Nested
 * calls join the outer transaction.
 */
interface TransactionPort
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $work
     * @return TResult
     */
    public function run(callable $work): mixed;
}
