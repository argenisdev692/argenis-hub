<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * A run cannot start as requested (FR-23, FR-25, US-12): empty scope, a stale
 * confirmed estimate, or an estimate above the ceilings.
 */
final class RunRequestRejectedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public readonly string $reasonCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function emptyScope(): self
    {
        return new self('empty_scope', 'There are no videos to generate in this scope.');
    }

    /**
     * @param  array<string, int>  $current
     */
    public static function estimateMismatch(array $current): self
    {
        return new self('estimate_mismatch', 'The estimate changed. Review and confirm it again.', ['estimate' => $current]);
    }

    /**
     * @param  array<string, int>  $estimate
     */
    public static function exceedsCeiling(array $estimate): self
    {
        return new self('estimate_exceeds_ceiling', 'This run would exceed the configured call ceiling. Choose a smaller scope.', ['estimate' => $estimate]);
    }

    public static function notRetryable(): self
    {
        return new self('nothing_to_retry', 'This run has no failed videos to retry, or it is still running.');
    }
}
