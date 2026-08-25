<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Formats E.164 phone numbers for human-facing output (tables, CSV / Excel / PDF
 * exports). Storage always stays E.164 (`+14155552671`); this only affects
 * display. An unparseable value falls back to the raw string so an export can
 * never break on a malformed number.
 */
final class PhoneFormatter
{
    /**
     * International format, e.g. `+1 787 987 3452`. Returns an em dash for an
     * empty value so export cells stay aligned.
     */
    public static function international(?string $e164, string $empty = '—'): string
    {
        if ($e164 === null || $e164 === '') {
            return $empty;
        }

        try {
            return (new PhoneNumber($e164))->formatInternational();
        } catch (Throwable) {
            return $e164;
        }
    }

    /**
     * National format, e.g. `(787) 987-3452` — the mandated export format
     * (BACKEND-PHP §8). Returns an em dash for an empty value so export cells
     * stay aligned.
     */
    public static function national(?string $e164, string $empty = '—'): string
    {
        if ($e164 === null || $e164 === '') {
            return $empty;
        }

        try {
            return (new PhoneNumber($e164))->formatNational();
        } catch (Throwable) {
            return $e164;
        }
    }
}
