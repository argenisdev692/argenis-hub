/**
 * Contact Support module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** One inbound support request as the admin inbox renders it. */
export type ContactSupport =
    Modules.ContactSupport.Application.DTOs.ContactSupportData;

/**
 * One page of the admin list, exactly as `AdminContactSupportController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta: {...}
 * }` prop-transformation shape, but `index()` calls `response()->json($paginator)`
 * directly, which serializes Laravel's own flat `LengthAwarePaginator::toArray()`
 * instead — `current_page`, `from`, `last_page`, `per_page`, `to` and `total`
 * sit at the top level, not nested under `meta`.
 */
export type ContactSupportPage = {
    data: ContactSupport[];
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
export type ContactSupportStatusFilter = 'all' | 'active' | 'deleted';

/** The `readed` inbox toggle as a tri-state select value. */
export type ReadStateFilter = 'all' | 'read' | 'unread';

/** The `is_spam` inbox toggle as a tri-state select value. */
export type SpamStateFilter = 'all' | 'spam' | 'ham';

/** The columns `ContactSupportEloquentModel::SORTABLE` whitelists. */
export type ContactSupportSortField =
    'subject' | 'email' | 'readed' | 'is_spam' | 'created_at' | 'updated_at';

/**
 * The query params `GET /data/admin/contact-supports` accepts.
 *
 * Snake_case throughout: `ContactSupportFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares. `readed` and
 * `is_spam` are UI tri-states here; `useContactSupports` narrows them to the
 * `bool | undefined` the backend actually reads.
 */
export type ContactSupportFilters = {
    search: string;
    status: ContactSupportStatusFilter;
    readed: ReadStateFilter;
    is_spam: SpamStateFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD` — `ContactSupportFilterData::$dateFrom`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD` — `ContactSupportFilterData::$dateTo`. */
    date_to: string | null;
    sort_field: ContactSupportSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** The exact body `POST` / `PUT /data/admin/contact-supports` accepts. */
export type ContactSupportWritePayload = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    subject: string;
    message: string;
    sms_consent: boolean;
    readed: boolean;
    is_spam: boolean;
};
