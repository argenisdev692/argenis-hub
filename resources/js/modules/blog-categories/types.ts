/**
 * Blog categories module types.
 *
 * Aliases over the generated declarations wherever the backend actually ships a
 * `Data` class. `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from those classes, so those names track
 * the backend on their own; writing the same shape out by hand is how a renamed
 * column turns into a runtime `undefined` instead of a build error.
 *
 * The row shapes below are the exception, and deliberately so: the Blog module
 * has no read-model DTO for the admin surface — `ListBlogCategoriesHandler`
 * returns a paginator of `BlogCategoryEloquentModel` and the controller hands it
 * straight to `response()->json()`. What arrives is therefore the Eloquent
 * serialization (the repository's `select()`, minus `$hidden = ['id']`, plus
 * `$appends = ['image_url']`), which no generated type describes. Each block
 * below names the exact source line it mirrors so the two can be re-checked
 * together.
 */

/**
 * `with('user:id,first_name,last_name')` on
 * `EloquentBlogCategoryRepository::paginate()`, minus the model's `$hidden`.
 * Null once the author's user row is gone.
 */
export type BlogCategoryAuthorRef = {
    first_name: string | null;
    last_name: string | null;
};

/**
 * One row of the admin list — exactly the columns
 * `EloquentBlogCategoryRepository::paginate()` selects, plus the appended
 * `image_url`.
 *
 * The name and description columns are nullable in the schema even though
 * `BlogCategoryData::rules()` makes `name` required on write: rows seeded before
 * that rule existed can still carry a null, and a table that assumes otherwise
 * renders the string "null" in the busiest column on the screen.
 */
export type BlogCategory = {
    uuid: string;
    blog_category_name: string | null;
    blog_category_description: string | null;
    /** The raw R2 object key. Render `image_url`, never this. */
    blog_category_image: string | null;
    /** Resolved through `StoragePort`; null with no image, or if R2 is down. */
    image_url: string | null;
    user_id: number;
    created_at: string;
    deleted_at: string | null;
    user: BlogCategoryAuthorRef | null;
};

/**
 * A single category as `GET /blog-categories/{uuid}` shares it —
 * `EloquentBlogCategoryRepository::findByUuid()`, which selects every column
 * but eager-loads nothing, so the author relation is absent rather than null.
 */
export type BlogCategoryDetail = Omit<BlogCategory, 'user'> & {
    updated_at: string;
};

/**
 * One page of the admin list, exactly as `BlogCategoryController::index()`
 * serializes it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>` ambient type: that stub models Inertia's `{ data, links, meta: {…} }`
 * prop-transformation shape, but `index()` calls `response()->json($categories)`
 * directly, which serializes Laravel's own flat `LengthAwarePaginator::toArray()`
 * instead — `current_page`, `from`, `last_page`, `per_page`, `to` and `total`
 * sit at the top level, not nested under `meta`.
 */
export type BlogCategoryPage = {
    data: BlogCategory[];
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
 * There is deliberately no "all": `EloquentBlogCategoryRepository::paginate()`
 * only branches on `'suspended'` (→ `onlyTrashed()`) and otherwise leaves the
 * `SoftDeletes` global scope in place, so active and trashed rows can never
 * appear in the same page. Offering an "All" option would show exactly the
 * active-only result the "Active" option already shows, which is worse than not
 * offering it — see the note in `Index.vue`.
 */
export type BlogCategoryStatusFilter = 'active' | 'suspended';

/**
 * The query params `GET /blog-categories` accepts.
 *
 * Snake_case throughout: `SoftDeleteFilterData` carries
 * `#[MapName(SnakeCaseMapper::class)]`, so this is the exact wire shape the
 * backend expects — not the camelCase property names PHP declares.
 *
 * No `sort_field` / `sort_order`: `paginate()` hard-codes
 * `orderByDesc('created_at')` and `BlogCategoryFilterData` has no sort
 * properties, so sending them would be ignored. The table therefore ships no
 * sortable columns rather than a control that silently does nothing.
 */
export type BlogCategoryFilters = {
    search: string;
    status: BlogCategoryStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD` — `$dateFrom`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD` — `$dateTo`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

/**
 * The exact multipart body `POST /blog-categories` and
 * `PUT /blog-categories/{uuid}` accept, as `BlogCategoryData` validates it.
 *
 * Every value is a `string` or a `File` because it rides a `FormData` body — a
 * boolean or a number would arrive at PHP as its string form anyway, and this
 * module has neither.
 *
 * `_method` is present only on update: PHP does not populate `$_FILES` for a
 * `PUT`, so the image upload is spoofed through `POST` exactly as a Blade
 * `@method('PUT')` form does.
 */
export type BlogCategoryWritePayload = {
    name: string;
    description?: string;
    image?: File;
    _method?: 'PUT';
};
