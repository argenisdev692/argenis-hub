/**
 * Availability module types.
 *
 * Aliases over the generated declarations wherever the backend actually ships a
 * `Data` class. `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from those classes, so those names track
 * the backend on their own; writing the same shape out by hand is how a renamed
 * column turns into a runtime `undefined` instead of a build error.
 *
 * The row shapes below are the exception, and deliberately so — the same
 * situation as `modules/blog-categories/types.ts`: this module has no read-model
 * DTO for the admin surface. `ListAvailabilityRulesHandler` /
 * `ListAvailabilityExceptionsHandler` return a paginator of the Eloquent model
 * and each controller hands it straight to `response()->json()`. What arrives is
 * therefore the Eloquent serialization (every column, minus `$hidden = ['id']`),
 * which no generated type describes. Each block names the exact source it
 * mirrors so the two can be re-checked together.
 *
 * ## On the write payloads
 *
 * `AvailabilityRuleData` and `AvailabilityExceptionData` both carry
 * `#[MapInputName(SnakeCaseMapper::class)]`, so the wire names are snake_case.
 * The generated file shows them camelCase because the transformer emits OUTPUT
 * names, and neither DTO is ever serialized outward. Sending `dayOfWeek` would
 * validate as a missing `day_of_week`, so the request shapes are spelled out
 * here in the casing the server actually reads.
 */

/** Provenance of a date exception — `ExceptionSource`, over the wire. */
export type ExceptionSource =
    Modules.Availability.Domain.ValueObjects.ExceptionSource;

/**
 * `0` = Sunday … `6` = Saturday, matching the `between:0,6` rule on
 * `AvailabilityRuleData` and PHP's own `Carbon::dayOfWeek`.
 */
export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6;

// ---- Weekly rules ----------------------------------------------------------

/**
 * One row of the weekly-rules list — the full
 * `AvailabilityRuleEloquentModel` serialization minus its `$hidden = ['id']`.
 *
 * `start_time` / `end_time` arrive as `HH:MM` strings: the `TimeOfDay` cast
 * trims the DB's `HH:MM:SS` down on read, which is also the format
 * `date_format:H:i` accepts on write, so a row round-trips without reshaping.
 */
export type AvailabilityRule = {
    uuid: string;
    day_of_week: DayOfWeek;
    start_time: string;
    end_time: string;
    is_available: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

/** `GET /availability-rules/{uuid}` — the same columns, fetched `withTrashed()`. */
export type AvailabilityRuleDetail = AvailabilityRule;

/**
 * The query params `GET /availability-rules` accepts when asked for JSON.
 *
 * There is no `search` axis, and that is not an omission: the table carries a
 * weekday, two times and a boolean — no free-text column exists to match
 * against, so a search box here would be a control that does nothing. The day
 * and availability selects are the filters this entity actually has.
 *
 * There is no sort axis either — `EloquentAvailabilityRuleRepository::paginate()`
 * hard-codes `orderBy('day_of_week')->orderBy('start_time')`, which is the only
 * order a weekly template reads correctly in.
 */
export type AvailabilityRuleFilters = {
    /** `'all'` is sent as an omitted param — see `buildAvailabilityRuleQueryParams`. */
    day_of_week: DayOfWeek | 'all';
    availability: 'all' | 'available' | 'unavailable';
    status: AvailabilityStatusFilter;
    page: number;
    per_page: number;
};

// ---- Date exceptions -------------------------------------------------------

/**
 * One row of the exceptions list — the full
 * `AvailabilityExceptionEloquentModel` serialization minus `$hidden = ['id']`.
 *
 * `date` is `YYYY-MM-DD` with no time component (the model casts it as
 * `date:Y-m-d` precisely so it never grows a midnight suffix). `start_time` /
 * `end_time` are null on a closure and `HH:MM` on a forced-open day.
 */
export type AvailabilityException = {
    uuid: string;
    date: string;
    is_available: boolean;
    start_time: string | null;
    end_time: string | null;
    reason: string | null;
    source: ExceptionSource;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

/** `GET /availability-exceptions/{uuid}` — same columns, fetched `withTrashed()`. */
export type AvailabilityExceptionDetail = AvailabilityException;

/**
 * The query params `GET /availability-exceptions` accepts when asked for JSON.
 *
 * Snake_case throughout: `AvailabilityExceptionFilterData` carries
 * `#[MapInputName(SnakeCaseMapper::class)]`.
 *
 * `date_from` / `date_to` bound the exception's OWN `date` column, not
 * `created_at` — an operator looking for "the December closures" means the days
 * being closed, not the day someone typed them in. The date-range filter on the
 * page is labelled accordingly.
 */
export type AvailabilityExceptionFilters = {
    search: string;
    availability: 'all' | 'open' | 'closed';
    status: AvailabilityStatusFilter;
    /** Inclusive `date` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `date` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

// ---- Shared -----------------------------------------------------------------

/**
 * Two options, not the usual three.
 *
 * Both repositories only branch on `suspended` (→ `onlyTrashed()`); every other
 * value leaves the `SoftDeletes` global scope in place. An "All" option would
 * therefore return exactly what "Active" returns, and a filter that quietly
 * does nothing is worse than one that is not offered — same call as
 * `BlogCategoryStatusFilter`.
 */
export type AvailabilityStatusFilter = 'active' | 'suspended';

/**
 * One page of either admin list.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey,
 * TValue>`: that stub models Inertia's `{ data, links, meta: {…} }`
 * prop-transformation shape, but both `index()` methods answer an XHR with
 * `response()->json($paginator)`, which serializes Laravel's own flat
 * `LengthAwarePaginator::toArray()` — `current_page`, `from`, `last_page`,
 * `per_page`, `to` and `total` sit at the top level, not nested under `meta`.
 */
export type AvailabilityPage<TRow> = {
    data: TRow[];
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

export type AvailabilityRulePage = AvailabilityPage<AvailabilityRule>;
export type AvailabilityExceptionPage = AvailabilityPage<AvailabilityException>;

/**
 * Request body for `POST`/`PUT /availability-rules` — the snake_case wire form
 * of `AvailabilityRuleData`.
 */
export type AvailabilityRuleWritePayload = {
    day_of_week: DayOfWeek;
    start_time: string;
    end_time: string;
    is_available: boolean;
};

/**
 * Request body for `POST`/`PUT /availability-exceptions` — the snake_case wire
 * form of `AvailabilityExceptionData`.
 *
 * The hours are omitted rather than sent as null on a closure: the DTO's rules
 * branch on `is_available`, and an omitted key hydrates the property as null,
 * which is exactly what the handler stores.
 */
export type AvailabilityExceptionWritePayload = {
    date: string;
    is_available: boolean;
    start_time?: string;
    end_time?: string;
    reason?: string;
};
