/**
 * Invoices module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** One invoice as the admin table renders it — no payment snapshot, by design. */
export type InvoiceListItem =
    Modules.Invoices.Application.DTOs.InvoiceListItemData;

/** One invoice with its lines, for the detail dialog and the edit form. */
export type InvoiceDetail = Modules.Invoices.Application.DTOs.InvoiceDetailData;

/** One persisted line, as the detail view reads it. */
export type InvoiceItemDetail =
    Modules.Invoices.Application.DTOs.InvoiceItemDetailData;

/** What a line is billing: a service, a course, a video, or free text. */
export type InvoiceItemKind = Modules.Invoices.Domain.Enums.InvoiceItemKind;

/** Unit a line is priced in — shared with the product catalog. */
export type BillingUnit = Shared.Domain.Enums.BillingUnit;

/** How the invoice was (or will be) settled. */
export type PaymentMethod = Modules.PaymentAccounts.Domain.Enums.PaymentMethod;

/**
 * One page of the admin list, exactly as `AdminInvoiceController::index()`
 * serializes it.
 *
 * Flat, not Inertia's `{ data, links, meta }`: `index()` calls
 * `response()->json($paginator)` directly, so Laravel's own
 * `LengthAwarePaginator::toArray()` shape is what arrives.
 */
export type InvoicePage = {
    data: InvoiceListItem[];
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
 * The soft-delete axis, as the toolbar's status select models it.
 *
 * `suspended` rather than `deleted`: `InvoiceFilterData` names it that way, and
 * the export's Status column agrees (BACKEND-PHP §8).
 */
export type InvoiceStatusFilter = 'all' | 'active' | 'suspended';

/**
 * The settlement axis — orthogonal to the soft-delete one, because a suspended
 * invoice can perfectly well have been paid.
 *
 * Applied by the server (`InvoiceFilterData::$paymentStatus`), never in the
 * browser: filtering the fifteen rows already on screen would report "3 unpaid"
 * when there are forty, and the export would disagree with the table.
 */
export type InvoicePaymentFilter = 'all' | 'paid' | 'unpaid';

/**
 * The query params `GET /data/admin/invoices` accepts.
 *
 * Snake_case throughout: `InvoiceFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape.
 */
export type InvoiceFilters = {
    search: string;
    status: InvoiceStatusFilter;
    payment_status: InvoicePaymentFilter;
    client_uuid: string | null;
    year: number | null;
    /** Inclusive `issue_date` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `issue_date` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

/** One line as the create/edit form models it, before it goes over the wire. */
export type InvoiceItemPayload = {
    title: string;
    description: string | null;
    kind: InvoiceItemKind;
    unit: BillingUnit;
    quantity: number;
    unit_price: number;
    service_uuid: string | null;
    product_uuid: string | null;
    sort_order: number;
};

/** The exact body `POST` / `PUT /data/admin/invoices` accepts. */
export type InvoiceWritePayload = {
    client_uuid: string;
    product_uuid: string | null;
    invoice_number: string;
    issue_date: string;
    due_date: string;
    currency: string;
    tax_mode: 'EXEMPT' | 'PERCENT';
    tax_rate: number | null;
    tax_label: string;
    is_paid: boolean;
    payment_method: PaymentMethod | null;
    payment_account_uuid: string | null;
    transfer_number: string | null;
    payment_date: string | null;
    amount_received: number | null;
    notes: string | null;
    additional_notes: string | null;
    items: InvoiceItemPayload[];
};

/** A client the invoice can be billed to, from `GET .../form-options`. */
export type InvoiceClientOption = {
    uuid: string;
    client_name: string;
    tax_id: string | null;
    nif: string | null;
    address: string | null;
    email: string | null;
    country: string | null;
    country_code: string | null;
};

/** A freelance service a line can reference. */
export type InvoiceServiceOption = {
    uuid: string;
    name: string;
    description: string | null;
};

/** A published catalog product a line can reference, with its billing defaults. */
export type InvoiceProductOption = {
    uuid: string;
    title: string;
    description: string | null;
    price: number | string;
    currency: string;
    type: string;
    default_unit: BillingUnit;
    total_hours: number | null;
};

/** A settlement rail the invoice can be paid through. */
export type InvoicePaymentAccountOption = {
    uuid: string;
    method: PaymentMethod;
    currency: string | null;
    label: string;
    is_default: boolean;
};

/**
 * Everything the create/edit form needs, in one request.
 *
 * The form cannot render usefully without all of it, so five separate queries
 * would only mean five separate loading states for one screen.
 */
export type InvoiceFormOptions = {
    clients: InvoiceClientOption[];
    services: InvoiceServiceOption[];
    products: InvoiceProductOption[];
    paymentAccounts: InvoicePaymentAccountOption[];
    paymentMethods: PaymentMethod[];
    itemKinds: InvoiceItemKind[];
    billingUnits: BillingUnit[];
    defaultNotes: string | null;
    issuerCountry: string;
};

/** `GET .../next-number` — the sequence the next invoice should take. */
export type NextInvoiceNumber = {
    invoice_number: string;
    sequence: number;
    year: number;
};

/**
 * `GET .../check-number` — whether a number is still free.
 *
 * `invoice_number` echoes back the *normalised* form, so a typed `14` returns
 * `014/2026` and the form can adopt the server's spelling rather than
 * reimplementing `CheckInvoiceNumberHandler::normalize()` in the browser.
 *
 * `invoice` names the row holding the number when it is taken — including a
 * suspended one, since a soft-deleted invoice still owns its sequence.
 */
export type InvoiceNumberCheck = {
    available: boolean;
    invoice_number: string;
    invoice: {
        uuid: string;
        invoice_number: string;
        client_name: string;
        is_suspended: boolean;
    } | null;
};
