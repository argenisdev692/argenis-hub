import { z } from 'zod';

/**
 * The client-side mirror of `BackupFilterData`'s validation rules.
 *
 * Backups have no mutating form — an archive is immutable and produced by the
 * scheduler or the on-demand run trigger, never authored — so this is the
 * module's one Zod schema: it types the filter state (`z.infer` in `types.ts`)
 * and seeds the pristine values (`defaultBackupFilters`). The server
 * re-validates every field; this is the single client-side source of truth for
 * the filter shape.
 *
 * `''` is the "unset" sentinel for the search axis and `'all'` for status; the
 * composable drops those keys from the request so the backend
 * `scopeApplyFilters` guards see an absent value.
 */
export const backupFilterSchema = z.object({
    /** Free text — matched against filename / connection / disk. */
    search: z.string().max(255).catch(''),
    /** Run outcome, or `'all'` for "any status" (sent as an omitted param). */
    status: z.enum(['all', 'running', 'completed', 'failed']).catch('all'),
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/)
        .nullable()
        .catch(null),
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/)
        .nullable()
        .catch(null),
    sort_field: z
        .enum(['filename', 'size_bytes', 'created_at'])
        .catch('created_at'),
    /** `1` ascending, `-1` descending — `BackupFilterData::$sortOrder`. */
    sort_order: z.union([z.literal(1), z.literal(-1)]).catch(-1),
    page: z.coerce.number().int().min(1).catch(1),
    per_page: z.coerce.number().int().min(1).max(100).catch(15),
});

export type BackupFilterValues = z.infer<typeof backupFilterSchema>;

export function defaultBackupFilters(): BackupFilterValues {
    return {
        search: '',
        status: 'all',
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}
