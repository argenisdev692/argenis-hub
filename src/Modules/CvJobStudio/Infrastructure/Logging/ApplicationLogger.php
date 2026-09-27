<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;
use Modules\CvJobStudio\Domain\Ports\StudioPipelineLoggerPort;

/**
 * Structured pipeline logger.
 *
 * Every CvJobStudio log line goes through here so posting text, fetched page
 * content, recruiter contact details and CV bodies can never reach the logs:
 * those are third-party and personal data, and the module minimises them out
 * of the pipeline before storage — the log must not be the way around that.
 *
 * The redaction is deliberately aggressive. A `raw_text`/`text`/`content` key
 * is blanked outright rather than truncated, so a future caller cannot widen
 * this by passing a longer field name.
 */
final readonly class ApplicationLogger implements StudioPipelineLoggerPort
{
    private const array REDACTED_KEYS = [
        'password', 'passwd', 'secret', 'token', 'bearer', 'authorization',
        'api_key', 'apikey', 'cookie', 'session', 'dsn', 'database_url',
        'private_key', 'client_secret',
        // Untrusted third-party content and personal data.
        'raw_text', 'text', 'markdown', 'content', 'content_markdown', 'body',
        'email', 'emails', 'phone', 'phones', 'full_name', 'address',
        'cv', 'cv_body', 'cv_text', 'responsibilities', 'requirement_text',
    ];

    /**
     * Keys that are safe to log even though a substring of theirs appears in
     * {@see self::REDACTED_KEYS}.
     *
     * This exists because matching is by substring: `canonical_name` holds a
     * skill ("Kubernetes"), not a person, and it is the one field that makes a
     * requirement-drop report diagnosable. Blanketing `name` would redact it
     * and leave an operator with a count and no cause. The list is a strict
     * allowlist — anything not named here and matching a redaction key is still
     * blanked — so widening the log surface cannot leak a personal name.
     */
    private const array ALLOWED_KEYS = [
        'canonical_name',
    ];

    public function pipeline(string $event, array $context = []): void
    {
        Log::info('cv-job-studio.'.$event, self::redact($context));
    }

    public function pipelineWarning(string $event, array $context = []): void
    {
        Log::warning('cv-job-studio.'.$event, self::redact($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public static function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (self::isSensitiveKey(mb_strtolower((string) $key))) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = self::scrubValue($value);
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::ALLOWED_KEYS, true)) {
            return false;
        }

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
