/**
 * CVs module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/**
 * One CV as `CvData` serializes it.
 *
 * The three columns that never cross the boundary — `id`, `user_id` and
 * `raw_text` (the full extracted résumé, the most sensitive PII the module
 * holds) — are absent from the DTO, so they are absent here too. `download_url`
 * is a short-lived signed R2 URL populated only by `CvController::show()`; on a
 * list row it is always `null`, which is why the table offers no download and
 * `Show.vue` does.
 */
export type Cv = Modules.Cvs.Application.DTOs.CvData;

/** The domain niche stored on `cvs.niche`, independent of soft-delete. */
export type CvNiche = Modules.Cvs.Domain.Enums.CvNiche;

/** Derived from the uploaded file's extension by `CvFileType::fromExtension()`. */
export type CvFileType = Modules.Cvs.Domain.Enums.CvFileType;

/**
 * One page of the list, exactly as `CvController::index()` serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta: {…} }`
 * prop-transformation shape, but `index()` calls
 * `response()->json(CvData::collect($paginator))`, and a `PaginatedDataCollection`
 * serializes Laravel's own flat `LengthAwarePaginator::toArray()` — verified
 * against the running app: `current_page`, `from`, `last_page`, `per_page`,
 * `to` and `total` sit at the top level, not nested under `meta`.
 */
export type CvPage = {
    data: Cv[];
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
 * The two states the list can be viewed in.
 *
 * There is deliberately no "all": `EloquentCvRepository::paginate()` only
 * branches on `'suspended'` (→ `onlyTrashed()`) and otherwise leaves the
 * `SoftDeletes` global scope in place, so active and trashed rows can never
 * share a page. `CvFilterData::rules()` backs this up with
 * `in:active,suspended`. Offering an "All" option would show exactly the
 * active-only result "Active" already shows — a filter that quietly does
 * nothing is worse than one that is not offered.
 */
export type CvStatusFilter = 'active' | 'suspended';

/** The niche facet, where the empty string means "no niche filter". */
export type CvNicheFilter = '' | CvNiche;

/**
 * The query params `GET /cvs` accepts.
 *
 * Snake_case throughout: `CvFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape
 * the backend expects — not the camelCase property names PHP declares.
 *
 * No `sort_field` / `sort_order`: `paginate()` hard-codes
 * `orderByDesc('is_primary')->orderByDesc('created_at')` — the primary CV
 * always leads — and `CvFilterData` has no sort properties, so sending them
 * would be ignored. The table therefore ships no sortable columns rather than
 * a control that silently does nothing.
 */
export type CvFilters = {
    search: string;
    status: CvStatusFilter;
    niche: CvNicheFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD` — `$dateFrom`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD` — `$dateTo`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

/**
 * The exact multipart body `POST /cvs` and `POST /cvs/{uuid}` accept, as
 * `UploadCvData` validates it.
 *
 * `file` is omitted rather than sent empty when the operator is only renaming a
 * CV: `UpdateCvHandler` replaces the stored R2 object *only* when a file
 * arrives, so an omitted key is what keeps the existing upload — and its
 * extracted `raw_text` — intact.
 */
export type CvWritePayload = {
    title: string;
    niche: CvNiche;
    is_primary: boolean;
    file?: File;
};
