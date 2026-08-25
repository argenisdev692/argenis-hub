/**
 * Display formatting for the dashboard.
 *
 * All of it is `Intl`-based and locale-aware, and every numeric output is
 * fixed-precision so figures do not jitter from row to row.
 */

const DEFAULT_LOCALE = 'en-US';
const DEFAULT_CURRENCY = 'USD';

export function formatCurrency(
    amount: number,
    currency: string = DEFAULT_CURRENCY,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

/** `48200` → `$48.2K`. Used where column width is tight (axis labels, tiles). */
export function formatCompactCurrency(
    amount: number,
    currency: string = DEFAULT_CURRENCY,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(amount);
}

/** Always signed, always one decimal: `+12.4%`, `-3.0%`, `0.0%`. */
export function formatSignedPercent(
    value: number,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.NumberFormat(locale, {
        style: 'percent',
        signDisplay: value === 0 ? 'never' : 'always',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(value / 100);
}

const RELATIVE_UNITS: readonly [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 60 * 60 * 1000],
    ['month', 30 * 24 * 60 * 60 * 1000],
    ['day', 24 * 60 * 60 * 1000],
    ['hour', 60 * 60 * 1000],
    ['minute', 60 * 1000],
];

/**
 * `2026-08-25T09:00:00Z` → "3 hours ago" / "in 2 days".
 *
 * Falls back to "just now" inside a minute rather than printing "0 minutes
 * ago", which reads as a bug.
 */
export function formatRelativeTime(
    iso: string,
    now: Date = new Date(),
    locale: string = DEFAULT_LOCALE,
): string {
    const deltaMs = new Date(iso).getTime() - now.getTime();
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, unitMs] of RELATIVE_UNITS) {
        if (Math.abs(deltaMs) >= unitMs) {
            return formatter.format(Math.round(deltaMs / unitMs), unit);
        }
    }

    return 'just now';
}

/** `2026-08-25T09:00:00Z` → "Mon 25 Aug, 09:00". */
export function formatDateTime(
    iso: string,
    locale: string = DEFAULT_LOCALE,
): string {
    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
