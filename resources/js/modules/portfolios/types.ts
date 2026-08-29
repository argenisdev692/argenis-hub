/**
 * Portfolios module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** A portfolio project as the admin table renders it. */
export type Portfolio = Modules.Portfolios.Application.DTOs.PortfolioData;

/** A published showcase entry, as `GET /api/public/portfolios` serializes it. */
export type PublicPortfolio =
    Modules.Portfolios.Application.DTOs.PublicPortfolioData;

/**
 * One page of the admin list, exactly as `AdminPortfolioController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta: {...} }`
 * prop-transformation shape, but `index()` calls `response()->json($paginator)`
 * directly, which serializes Laravel's own flat `LengthAwarePaginator::toArray()`
 * instead — `current_page`, `from`, `last_page`, `per_page`, `to` and `total`
 * sit at the top level, not nested under `meta`. Same shape as `ServicePage`.
 */
export type PortfolioPage = {
    data: Portfolio[];
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

export type PortfolioStatusFilter = 'all' | 'active' | 'deleted';

/**
 * The whitelisted sort columns — `PortfolioEloquentModel::SORTABLE`. Anything
 * else is coerced to `created_at` on the server, so keep this in step with it.
 */
export type PortfolioSortField =
    | 'title'
    | 'client_name'
    | 'published_at'
    | 'sort_order'
    | 'created_at'
    | 'updated_at';

/**
 * The query params `GET /data/admin/portfolios` accepts.
 *
 * Snake_case throughout: `PortfolioFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares. `status` is
 * the soft-delete axis (`active` / `deleted` / all), not the `is_public` flag.
 */
export type PortfolioFilters = {
    search: string;
    status: PortfolioStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD` — `PortfolioFilterData::$dateFrom`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD` — `PortfolioFilterData::$dateTo`. */
    date_to: string | null;
    sort_field: PortfolioSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/**
 * The exact body `POST` / `PUT /data/admin/portfolios` accepts — mirrors
 * `StorePortfolioRequest` / `UpdatePortfolioRequest`.
 *
 * `cover_path`, `video_path` and `media` are R2 object keys (plain strings),
 * NOT uploads: this project has no upload endpoint, so the admin form edits the
 * keys directly and the gallery is an ordered list of them.
 */
export type PortfolioWritePayload = {
    title: string;
    client_name: string;
    project_type: string;
    tech_stack: string[];
    live_url: string | null;
    published_at: string | null;
    is_public: boolean;
    cover_path: string | null;
    video_path: string | null;
    description: string | null;
    sort_order: number;
    media: string[];
};
