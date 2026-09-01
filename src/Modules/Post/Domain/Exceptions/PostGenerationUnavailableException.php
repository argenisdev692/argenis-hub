<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A provider the generation depends on is refusing calls outright — the shared
 * circuit breaker is open for it.
 *
 * This is NOT the same failure as "the provider returned an error". A single
 * error is worth retrying: the next iteration might succeed. An open breaker
 * means the last five calls already failed and every further call is rejected
 * locally without leaving the process, so the remaining iterations cannot
 * possibly do anything except produce log noise and end on a message that says
 * nothing useful.
 *
 * The Infrastructure adapters translate the resilience layer's own
 * `CircuitBreakerOpenException` into this one — deliberately named in prose
 * rather than with a `{@see}` tag, because a link here would be turned into a
 * real `use` statement by the formatter and Domain does not import
 * Infrastructure. The point of this class is exactly that: Application can
 * react to "the provider is down" without knowing what decided it.
 */
final class PostGenerationUnavailableException extends RuntimeException
{
    public static function forService(string $service, ?Throwable $previous = null): self
    {
        return new self(
            "The AI provider is temporarily unavailable ([{$service}] circuit breaker is open). "
                .'Wait for it to recover and run the generation again.',
            previous: $previous,
        );
    }
}
