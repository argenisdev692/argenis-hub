/**
 * Clients module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** One CRM client as the admin table renders it. */
export type Client = Modules.Clients.Application.DTOs.ClientData;

/** The CRM lifecycle axis (`clients.status`), independent of soft-delete. */
export type ClientStatus = Modules.Clients.Domain.Enums.ClientStatus;

/**
 * One page of the admin list, exactly as `AdminClientController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta: {...}
 * }` prop-transformation shape, but `index()` calls `response()->json($paginator)`
 * directly, which serializes Laravel's own flat `LengthAwarePaginator::toArray()`
 * instead — `current_page`, `from`, `last_page`, `per_page`, `to` and `total`
 * sit at the top level, not nested under `meta`.
 */
export type ClientPage = {
    data: Client[];
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

/** The soft-delete axis, as the toolbar's status select models it. */
export type ClientStatusFilter = 'all' | 'active' | 'deleted';

/** The columns `ClientEloquentModel::SORTABLE` whitelists. */
export type ClientSortField =
    'created_at' | 'updated_at' | 'client_name' | 'status';

/**
 * The query params `GET /data/admin/clients` accepts.
 *
 * Snake_case throughout: `ClientFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares. `status`
 * here is the soft-delete axis (`active` / `deleted` / all), not the CRM
 * lifecycle column.
 */
export type ClientFilters = {
    search: string;
    status: ClientStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD` — `ClientFilterData::$dateFrom`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD` — `ClientFilterData::$dateTo`. */
    date_to: string | null;
    sort_field: ClientSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** The exact body `POST` / `PUT /data/admin/clients` accepts. */
export type ClientWritePayload = {
    client_name: string;
    email: string | null;
    status: ClientStatus;
    phone: string;
    address: string | null;
    country: string | null;
    country_code: string | null;
    tax_id: string | null;
    nif: string | null;
    website: string | null;
    facebook_link: string | null;
    instagram_link: string | null;
    linkedin_link: string | null;
    twitter_link: string | null;
    notes: string | null;
};
