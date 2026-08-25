<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Resilience\CircuitBreaker;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache-backed circuit breaker (Laravel 13 ships no first-party one).
 *
 * State lives in Redis (the project's pinned cache store), so the breaker is
 * shared across web/queue workers. Wrap ONLY outbound network calls with it
 * (AI provider, Resend, R2) — never local/synchronous work.
 *
 * State machine:
 *   Closed  → failures < threshold
 *   Open    → threshold reached; rejects until cooldown elapses
 *   HalfOpen→ cooldown elapsed; next call is a trial (success closes, failure re-opens)
 */
final class CircuitBreaker implements CircuitBreakerInterface
{
    private const PREFIX = 'circuit_breaker:';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 30,
    ) {}

    public function call(string $service, \Closure $operation, ?\Closure $fallback = null): mixed
    {
        if ($this->state($service) === CircuitBreakerState::Open) {
            $exception = CircuitBreakerOpenException::for($service);

            return $fallback !== null ? $fallback($exception) : throw $exception;
        }

        try {
            $result = $operation();
            $this->onSuccess($service);

            return $result;
        } catch (\Throwable $e) {
            $this->onFailure($service);

            if ($fallback !== null) {
                return $fallback($e);
            }

            throw $e;
        }
    }

    public function state(string $service): CircuitBreakerState
    {
        $openedAt = $this->cache->get($this->key($service, 'opened_at'));

        return match (true) {
            $openedAt === null => CircuitBreakerState::Closed,
            (int) $openedAt + $this->cooldownSeconds > time() => CircuitBreakerState::Open,
            default => CircuitBreakerState::HalfOpen,
        };
    }

    private function onSuccess(string $service): void
    {
        $this->cache->forget($this->key($service, 'failures'));
        $this->cache->forget($this->key($service, 'opened_at'));
    }

    private function onFailure(string $service): void
    {
        // A failure during the trial call re-opens immediately.
        if ($this->state($service) === CircuitBreakerState::HalfOpen) {
            $this->open($service);

            return;
        }

        $failures = (int) $this->cache->get($this->key($service, 'failures'), 0) + 1;

        if ($failures >= $this->failureThreshold) {
            $this->open($service);

            return;
        }

        $this->cache->put($this->key($service, 'failures'), $failures, $this->cooldownSeconds * 2);
    }

    private function open(string $service): void
    {
        // TTL > cooldown so the breaker can sit in HalfOpen before auto-closing.
        $this->cache->put($this->key($service, 'opened_at'), time(), $this->cooldownSeconds * 4);
        $this->cache->forget($this->key($service, 'failures'));
    }

    private function key(string $service, string $suffix): string
    {
        return self::PREFIX.$service.':'.$suffix;
    }
}
