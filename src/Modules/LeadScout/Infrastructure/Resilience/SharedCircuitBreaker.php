<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Resilience;

use Closure;
use Modules\LeadScout\Domain\Ports\CircuitBreakerPort;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;

/**
 * The module's breaker port on top of the shared Redis-backed breaker.
 */
final readonly class SharedCircuitBreaker implements CircuitBreakerPort
{
    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function call(string $service, Closure $operation, ?Closure $fallback = null): mixed
    {
        return $this->breaker->call($service, $operation, $fallback);
    }
}
