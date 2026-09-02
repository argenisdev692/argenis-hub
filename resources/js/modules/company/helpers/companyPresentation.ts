/**
 * Display formatting for the company record.
 *
 * `CompanyProfileData.updated_at` arrives as the ISO 8601 string
 * `CompanyMapper` writes (`$model->updated_at?->toIso8601String()`), which is a
 * wire format, not a reading format — rendered straight into the template it
 * reaches the operator as `2026-09-02T14:33:21+00:00`. The conversion lives
 * here, next to the module's other projections, and matches the
 * `formatDate` / `formatDateTime` pair every sibling module already ships.
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
