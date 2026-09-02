/**
 * PaymentAccounts module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** One settlement rail as the admin table renders it. */
export type PaymentAccount =
    Modules.PaymentAccounts.Application.DTOs.PaymentAccountData;

/** How money moves — deliberately independent of currency. */
export type PaymentMethod = Modules.PaymentAccounts.Domain.Enums.PaymentMethod;

/**
 * One page of the admin list, exactly as
 * `AdminPaymentAccountController::index()` serializes it.
 *
 * Flat, not Inertia's `{ data, links, meta }`: `index()` calls
 * `response()->json($paginator)` directly, so Laravel's own
 * `LengthAwarePaginator::toArray()` shape is what arrives.
 */
export type PaymentAccountPage = {
    data: PaymentAccount[];
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
export type PaymentAccountStatusFilter = 'all' | 'active' | 'deleted';

/** The columns `PaymentAccountFilterData::$sortField` whitelists. */
export type PaymentAccountSortField =
    | 'created_at'
    | 'label'
    | 'method'
    | 'currency';

/**
 * The query params `GET /data/admin/payment-accounts` accepts.
 *
 * Snake_case throughout: `PaymentAccountFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects. `method` and `currency` are separate axes on purpose —
 * "every USD rail" and "every Remitly rail" are different questions.
 */
export type PaymentAccountFilters = {
    search: string;
    status: PaymentAccountStatusFilter;
    method: PaymentMethod | null;
    currency: string | null;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    sort_field: PaymentAccountSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** The exact body `POST` / `PUT /data/admin/payment-accounts` accepts. */
export type PaymentAccountWritePayload =
    Modules.PaymentAccounts.Application.DTOs.StorePaymentAccountData;
