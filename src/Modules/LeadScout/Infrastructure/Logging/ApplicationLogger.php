<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;

/**
 * Structured pipeline logger (spec T076): every LeadScout log line goes
 * through here so secrets, contact PII, draft bodies and the Supabase
 * connection string can never reach the logs. Suspicious keys are blanked,
 * emails inside string values are masked, connection-like values dropped.
 */
final readonly class ApplicationLogger
{
    private const array REDACTED_KEYS = [
        'password', 'passwd', 'secret', 'token', 'bearer', 'authorization',
        'api_key', 'apikey', 'key', 'cookie', 'session', 'dsn', 'database_url',
        'connection', 'supabase', 'private_key', 'client_secret',
        'draft_body', 'draft', 'raw_text', 'markdown', 'content_markdown',
        'published_email', 'email', 'full_name', 'name', 'phone', 'address',
    ];

    public function pipeline(string $event, array $context = []): void
    {
        Log::info('lead-scout.'.$event, self::redact($context));
    }

    public function pipelineWarning(string $event, array $context = []): void
    {
        Log::warning('lead-scout.'.$event, self::redact($context));
    }

    /**
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public static function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $flat = mb_strtolower((string) $key);

            if (self::isSensitiveKey($flat)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = self::scrubValue($value);
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function scrubValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::redact($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        // Connection strings and signed URLs never reach logs.
        if (preg_match('#^[a-z][a-z0-9+.-]*://[^\\s]*@[^\\s]*$#i', trim($value)) === 1) {
            return '[redacted]';
        }

        $masked = (string) preg_replace(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '[email]',
            $value,
        );

        return mb_strlen($masked) > 1000 ? mb_substr($masked, 0, 1000).'…' : $masked;
    }
}
