<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

/**
 * Retryable vs terminal failures (T-152, RK-20): connection errors, 429 and
 * 502/504/520/522/524 fail over; invalid structured output and 4xx never do —
 * retrying a malformed schema on another provider only bills twice.
 */
final readonly class FailoverPolicy
{
    #[\NoDiscard]
    public function isRetryable(\Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        foreach (['connection', 'timed out', 'timeout', '429', '502', '504', '520', '522', '524', 'rate limit', 'overloaded', 'unavailable'] as $signal) {
            if (str_contains($message, $signal)) {
                return true;
            }
        }

        foreach (['schema', 'validation', 'invalid output', '400', '401', '403', '404', '422'] as $terminal) {
            if (str_contains($message, $terminal)) {
                return false;
            }
        }

        return true;
    }
}
