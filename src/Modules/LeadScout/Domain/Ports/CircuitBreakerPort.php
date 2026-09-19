<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Closure;
use Throwable;

/**
 * Isolates a failing upstream (job source, search, extraction) so one bad
 * provider cannot stall the pipeline (spec US-2 CA-4).
 */
interface CircuitBreakerPort
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @param  (Closure(Throwable): TResult)|null  $fallback
     * @return TResult
     */
    public function call(string $service, Closure $operation, ?Closure $fallback = null): mixed;
}
