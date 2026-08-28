import type { ClientStatus } from '../types';

/**
 * Row-derived display values shared by the table, the detail dialog and the
 * delete confirmations, so "how a client is labelled" is decided once.
 */

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

/** Human-readable label for the CRM lifecycle enum. */
const STATUS_LABELS: Record<ClientStatus, string> = {
    DRAFT: 'Draft',
    ACTIVE: 'Active',
    INACTIVE: 'Inactive',
};

export function clientStatusLabel(status: ClientStatus): string {
    return STATUS_LABELS[status];
}

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

/** Badge tone per lifecycle state — a live client stands out, a draft is muted. */
const STATUS_VARIANTS: Record<ClientStatus, BadgeVariant> = {
    ACTIVE: 'default',
    INACTIVE: 'secondary',
    DRAFT: 'outline',
};

export function clientStatusVariant(status: ClientStatus): BadgeVariant {
    return STATUS_VARIANTS[status];
}
