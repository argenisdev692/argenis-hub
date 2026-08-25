<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Resilience\CircuitBreaker;

final class CircuitBreakerOpenException extends \RuntimeException
{
    public static function for(string $service): self
    {
        return new self("Circuit breaker for service [{$service}] is open.");
    }
}
