/**
 * Authorization module types — roles and permissions.
 *
 * Write payloads are aliases over the generated declarations
 * (`php artisan typescript:transform` from the Spatie `Data` classes), so a
 * renamed field is a build error rather than a runtime `undefined`.
 *
 * The *row* shapes below are hand-written, deliberately. Unlike Clients or
 * Services, `RoleController::index` paginates Eloquent models rather than a
 * `Data` collection — there is no `RoleData` read DTO to generate from, so the
 * shape is dictated by the repository's `select()` + `with()` + `withCount()`
 * (see `EloquentRoleRepository::paginate()`). Keep the two in step: a column
 * dropped from that `select()` must be dropped here too.
 */

/** The permission-name catalogue the role form offers, as `RoleController::index` shares it. */
export type PermissionName = string;

/**
 * One role as the admin table renders it.
 *
 * `id` never crosses the wire (`Role::$hidden`); `uuid` is the public
 * identifier every route binds on.
 */
export type Role = {
    uuid: string;
    name: string;
    guard_name: string;
    created_at: string | null;
    /** Non-null while the role is suspended (soft-deleted). */
    deleted_at: string | null;
    permissions_count: number;
    permissions: readonly { name: PermissionName }[];
};

/**
 * One role as `GET /roles/{uuid}` renders it.
 *
 * `findByUuid()` eager-loads `permissions` but does not `withCount()`, so the
 * detail payload is the row minus its counter — not a different entity.
 */
export type RoleDetail = Omit<Role, 'permissions_count'>;

/** One permission as the admin table renders it. */
export type Permission = {
    uuid: string;
    name: PermissionName;
    guard_name: string;
    created_at: string | null;
    /** Non-null while the permission is suspended (soft-deleted). */
    deleted_at: string | null;
    roles_count: number;
    roles: readonly { name: string }[];
};

/**
 * One permission as `GET /permissions/{uuid}` renders it.
 *
 * `EloquentPermissionRepository::findByUuid()` loads neither the `roles`
 * relation nor its count, so the detail page shows the record alone.
 */
export type PermissionDetail = Omit<Permission, 'roles_count' | 'roles'>;

/**
 * One page of an admin list, exactly as the controllers serialize it.
 *
 * Deliberately NOT the generated `Illuminate.LengthAwarePaginator<TKey, TValue>`
 * ambient type: that stub models Inertia's `{ data, links, meta: {...} }`
 * prop-transformation shape, but `index()` calls `response()->json($paginator)`,
 * which serializes Laravel's own flat `LengthAwarePaginator::toArray()` —
 * `current_page`, `from`, `last_page`, `per_page`, `to` and `total` sit at the
 * top level, not nested under `meta`.
 */
export type AuthorizationPage<TRow> = {
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

export type RolePage = AuthorizationPage<Role>;
export type PermissionPage = AuthorizationPage<Permission>;

/**
 * The soft-delete axis, as the toolbar's status select models it.
 *
 * Only two states, not the usual three: `RoleFilterData` / `PermissionFilterData`
 * validate `in:active,suspended`, and the repositories apply `onlyTrashed()` for
 * `suspended` while the default query inherits the `SoftDeletes` global scope.
 * There is no "both" case on the backend, so offering an "All" option here would
 * be a control that quietly does nothing.
 */
export type AuthorizationStatusFilter = 'active' | 'suspended';

/** The wire shape `GET /roles` accepts, per the generated filter DTO. */
type RoleFilterWire = Modules.Authorization.Application.DTOs.RoleFilterData;

/** The wire shape `GET /permissions` accepts, per the generated filter DTO. */
type PermissionFilterWire =
    Modules.Authorization.Application.DTOs.PermissionFilterData;

/**
 * The query params the role list sends.
 *
 * `search` / `status` / `date_from` / `date_to` are the filter DTO's own keys —
 * `queryParams` in `useRoles` is typed against `RoleFilterWire` so a renamed
 * backend field breaks the build here. `page` and `per_page` are the paginator's,
 * read straight off the request rather than through the DTO.
 *
 * Note there is no `sort_field` / `sort_order`: both repositories hard-code
 * `orderBy('name')`, so the tables expose no sortable columns rather than
 * offering a control the server ignores.
 */
export type RoleFilters = {
    search: string;
    status: AuthorizationStatusFilter;
    /** Inclusive `created_at` lower bound, `YYYY-MM-DD`. */
    date_from: string | null;
    /** Inclusive `created_at` upper bound, `YYYY-MM-DD`. */
    date_to: string | null;
    page: number;
    per_page: number;
};

/** Same shape as {@see RoleFilters} — the two filter DTOs are siblings. */
export type PermissionFilters = RoleFilters;

/** The exact params sent to `GET /roles` and `GET /roles/export`. */
export type RoleQueryParams = Partial<RoleFilterWire> & {
    page?: number;
    per_page?: number;
};

/** The exact params sent to `GET /permissions` and `GET /permissions/export`. */
export type PermissionQueryParams = Partial<PermissionFilterWire> & {
    page?: number;
    per_page?: number;
};

/** The exact body `POST /roles` and `PUT /roles/{uuid}` accept. */
export type RoleWritePayload = Modules.Authorization.Application.DTOs.RoleData;

/** The exact body `POST /permissions` and `PUT /permissions/{uuid}` accept. */
export type PermissionWritePayload =
    Modules.Authorization.Application.DTOs.PermissionData;
