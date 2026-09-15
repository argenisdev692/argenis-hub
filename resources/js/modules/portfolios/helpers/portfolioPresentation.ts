/**
 * Portfolio presentation helpers — pure ISO → display-string projections.
 *
 * Every sibling module keeps its own `{entity}Presentation.ts` rather than
 * importing another module's (only `types.ts` may be shared across modules),
 * and the table page never formats inline — see `servicePresentation.ts`.
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
