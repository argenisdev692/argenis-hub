/**
 * Shared vocabulary for the table kit.
 *
 * Deliberately free of any transport concern: nothing here knows whether rows
 * arrive from an Inertia prop, a Pinia Colada query or a fixture. `DataTable`
 * renders what it is handed and reports intent upward, which is what lets the
 * same component serve a server-paginated list and a static one.
 */

export type SortDirection = 'asc' | 'desc';

/**
 * An inclusive `created_at` window, both ends `YYYY-MM-DD` or null.
 *
 * Deliberately a pair of ISO date strings, never JS `Date`s: that is what
 * Laravel's `date` rule and an Eloquent `date` cast both expect, and it keeps a
 * timezone out of a value that has none (see `common/form/DatePickerInput.vue`).
 */
export type DateRange = {
    from: string | null;
    to: string | null;
};

/** The formats the Shared `ExportPort` can stream a filtered list as. */
export type ExportFormat = 'xlsx' | 'csv' | 'pdf';

export type DataTableSort = {
    /** Matches the `key` of the column being sorted. */
    field: string;
    direction: SortDirection;
};

export type ColumnAlign = 'left' | 'center' | 'right';

/**
 * One column of a `DataTable`.
 *
 * Supply `value` for the common case of "render this field as text". For
 * anything richer — a badge, a link, an avatar — omit it and fill the
 * `cell:{key}` slot instead; `DataTable` prefers the slot when both exist.
 */
export type DataTableColumn<TRow> = {
    /** Unique within the table. Also the slot name and the sort field. */
    key: string;
    header: string;
    value?: (row: TRow) => string | number | null | undefined;
    sortable?: boolean;
    align?: ColumnAlign;
    /** Utility classes for the header and body cells, e.g. `'w-40'`. */
    class?: string;
    /** Drop the column below the `md` breakpoint. */
    hideOnMobile?: boolean;
};

/**
 * Laravel's paginator meta block, snake_case as it arrives over the wire.
 *
 * `from`/`to` are null on an empty page, which is why the record counter reads
 * them defensively rather than assuming a range exists.
 */
export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
