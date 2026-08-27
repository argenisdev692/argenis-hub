/**
 * Timestamp formatting for the backups screen.
 *
 * A local copy rather than an import from another module's `format` helper — a
 * module reaching sideways into another's helpers is how two features end up
 * welded together. It is a few lines of `Intl`; the duplication is cheaper than
 * the coupling (same reasoning as `activity-log/helpers/formatActivityTimestamp`).
 */

const LOCALE = 'en-US';

/** `2026-08-27T09:00:00Z` → "27 Aug 2026, 09:00"; null → "—". */
export function formatBackupTimestamp(iso: string | null): string {
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
