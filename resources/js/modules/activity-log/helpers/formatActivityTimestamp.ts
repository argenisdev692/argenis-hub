/**
 * Timestamp formatting for the activity trail.
 *
 * A local copy rather than an import from `modules/dashboard/helpers/format` —
 * a module reaching sideways into another module's helpers is how two features
 * end up welded together. Both are a few lines of `Intl`; the duplication is
 * cheaper than the coupling.
 */

const LOCALE = 'en-US';

const RELATIVE_UNITS: readonly [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 60 * 60 * 1000],
    ['month', 30 * 24 * 60 * 60 * 1000],
    ['day', 24 * 60 * 60 * 1000],
    ['hour', 60 * 60 * 1000],
    ['minute', 60 * 1000],
];

/** `2026-08-25T09:00:00Z` → "3 hours ago"; under a minute → "just now". */
export function formatActivityRelative(
    iso: string | null,
    now: Date = new Date(),
): string {
    if (!iso) {
        return '—';
    }

    const deltaMs = new Date(iso).getTime() - now.getTime();
    const formatter = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

    for (const [unit, unitMs] of RELATIVE_UNITS) {
        if (Math.abs(deltaMs) >= unitMs) {
            return formatter.format(Math.round(deltaMs / unitMs), unit);
        }
    }

    return 'just now';
}

/** `2026-08-25T09:00:00Z` → "25 Aug 2026, 09:00". */
export function formatActivityAbsolute(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat(LOCALE, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
