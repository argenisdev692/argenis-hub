/**
 * Services module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** A catalog service as the admin table renders it. */
export type Service = Modules.Services.Application.DTOs.ServiceData;

/**
 * One page of the admin list, exactly as `AdminServiceController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub (`spatie/laravel-typescript-transformer`)
 * models Inertia's `{ data, links, meta: {...} }` prop-transformation shape,
 * but `index()` calls `response()->json($paginator)` directly, which
 * serializes Laravel's own flat `LengthAwarePaginator::toArray()` instead —
 * `current_page`, `from`, `last_page`, `per_page`, `to` and `total` sit at
 * the top level, not nested under `meta`.
 */
export type ServicePage = {
    data: Service[];
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

export type ServiceStatusFilter = 'all' | 'active' | 'deleted';

/**
 * The query params `GET /data/admin/services` accepts.
 *
 * Snake_case throughout: `ServiceFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape
 * the backend expects — not the camelCase property names PHP declares.
 */
export type ServiceFilters = {
    search: string;
    status: ServiceStatusFilter;
    sort_field: 'name' | 'slug' | 'sort_order' | 'created_at';
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** The exact body `POST` / `PUT /data/admin/services` accepts. */
export type ServiceWritePayload = {
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
};
