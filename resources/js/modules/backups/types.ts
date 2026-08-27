/**
 * Backups module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 *
 * A backup archive is immutable: there is no write payload type here and never
 * will be — the backend exposes `index` / `show` / `store` (trigger a run) /
 * `destroy` / `bulk-delete` / `download` / `export`, none of which take a body
 * of editable fields.
 */

import type { z } from 'zod';
import type { backupFilterSchema } from './schemas/backupFilterSchema';

/** One backup archive (or failed attempt) as the admin table renders it. */
export type Backup = Modules.Backups.Application.DTOs.BackupData;

/** The lifecycle of a single backup run. */
export type BackupStatus = Modules.Backups.Domain.Enums.BackupStatus;

/** `'all'` plus every concrete run status — the values the status filter offers. */
export type BackupStatusFilter = 'all' | BackupStatus;

/**
 * One page of the admin list, exactly as `AdminBackupController::index()`
 * serializes it: `response()->json($paginator)` emits Laravel's own flat
 * `LengthAwarePaginator::toArray()` shape, not Inertia's `{ data, meta }` one —
 * same reasoning as `ServicePage`.
 */
export type BackupPage = {
    data: Backup[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: { url: string | null; label: string; active: boolean }[];
};

/**
 * The UI filter state. Its shape and its runtime coercion both come from the
 * Zod schema (single source of truth), so this is just `z.infer` of it.
 *
 * Snake_case throughout: `BackupFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares.
 */
export type BackupFilters = z.infer<typeof backupFilterSchema>;
