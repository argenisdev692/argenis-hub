/**
 * Products module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 */

/** One catalog product as the admin table renders it. */
export type Product = Modules.Products.Application.DTOs.ProductData;

/** Course / video course / workshop / mentoring. */
export type ProductType = Modules.Products.Domain.Enums.ProductType;

/** The catalog publication axis, independent of soft-delete. */
export type ProductStatus = Modules.Products.Domain.Enums.ProductStatus;

/** Unit a line is priced in — shared with invoice items. */
export type BillingUnit = Shared.Domain.Enums.BillingUnit;

/**
 * One page of the admin list, exactly as `AdminProductController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta }`
 * prop-transformation shape, but `index()` calls `response()->json($paginator)`
 * directly, which serializes Laravel's own flat `LengthAwarePaginator::toArray()`
 * instead — `current_page`, `from`, `last_page`, `per_page`, `to` and `total`
 * sit at the top level, not nested under `meta`.
 */
export type ProductPage = {
    data: Product[];
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
export type ProductRowStatusFilter = 'all' | 'active' | 'deleted';

/** The columns `ProductFilterData::$sortField` whitelists. */
export type ProductSortField =
    | 'created_at'
    | 'title'
    | 'price'
    | 'start_date'
    | 'status';

/**
 * The query params `GET /data/admin/products` accepts.
 *
 * Snake_case throughout: `ProductFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares. `status`
 * here is the soft-delete axis; `product_status` is the catalog column.
 */
export type ProductFilters = {
    search: string;
    status: ProductRowStatusFilter;
    type: ProductType | null;
    product_status: ProductStatus | null;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    sort_field: ProductSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** The exact body `POST` / `PUT /data/admin/products` accepts. */
export type ProductWritePayload =
    Modules.Products.Application.DTOs.StoreProductData;
