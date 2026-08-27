import type { ContactSupport } from '../types';

/**
 * Row-derived display values shared by the table, the detail dialog and the
 * delete confirmations, so "how a request is labelled" is decided once.
 */

/** `first_name` + `last_name`, collapsed and trimmed. */
export function contactName(support: ContactSupport): string {
    return `${support.first_name} ${support.last_name}`.trim();
}

/** ISO8601 → "3 Jun 2026", or `null` when there is no timestamp. */
export function formatDate(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

/** ISO8601 → "3 Jun 2026, 14:05", or `null` when there is no timestamp. */
export function formatDateTime(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}
