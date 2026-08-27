import { z } from 'zod';

/**
 * The client-side mirror of `ActivityLogFilterData::rules()`.
 *
 * The trail has no mutating form, so this is the module's one Zod schema: it
 * types the filter state (`z.infer` in `types.ts`) and seeds the pristine
 * values (`defaultActivityLogFilters`). The server re-validates every field;
 * this is the single client-side source of truth for the filter shape.
 *
 * `''` is the "unset" sentinel for every text axis (an empty `<input>` and an
 * unselected `FilterSelect` both yield it); the composable drops those keys
 * from the request so the backend `when(filled(...))` guards see `null`.
 */
export const activityLogFilterSchema = z.object({
    /** Free text — matched against description / event / log name / subject. */
    search: z.string().max(255).catch(''),
    /** Exact `event` match, or `''` for "any event". */
    event: z.enum(['', 'created', 'updated', 'deleted', 'restored']).catch(''),
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
    /** Newest-first by default; the only column the trail sorts on is time. */
    sort_direction: z.enum(['asc', 'desc']).catch('desc'),
    page: z.coerce.number().int().min(1).catch(1),
    per_page: z.coerce.number().int().min(1).max(100).catch(20),
});

export type ActivityLogFilterValues = z.infer<typeof activityLogFilterSchema>;

export function defaultActivityLogFilters(): ActivityLogFilterValues {
    return {
        search: '',
        event: '',
        date_from: null,
        date_to: null,
        sort_direction: 'desc',
        page: 1,
        per_page: 20,
    };
}
