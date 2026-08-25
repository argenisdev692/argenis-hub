<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Resilience\CircuitBreaker;

interface CircuitBreakerInterface
{
    /**
     * Execute $operation guarded by the breaker for the logical $service key.
     *
     * - Closed/HalfOpen: runs $operation, recording success/failure.
     * - Open: short-circuits — invokes $fallback if given, else throws
     *   CircuitBreakerOpenException.
     *
     * @template T
     *
     * @param  \Closure(): T  $operation
     * @param  (\Closure(\Throwable): T)|null  $fallback
     * @return T
     */
    public function call(string $service, \Closure $operation, ?\Closure $fallback = null): mixed;

    public function state(string $service): CircuitBreakerState;
}
