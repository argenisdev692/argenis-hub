<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const string GUARD = 'web';

    /** @var list<string> */
    private const array ROLES = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'USER', 'GUEST'];

    /**
     * Standard modules — each gets the full action set below.
     * Permission name format: "{ACTION}_{MODULE}" (e.g. "VIEW_ANY_USERS").
     *
     * @var list<string>
     */
    private const array MODULES = ['USERS', 'ROLES', 'PERMISSIONS', 'BLOG_CATEGORIES', 'POSTS', 'SOCIAL_MEDIA', 'CAMPAIGNS', 'CONTACT_SUPPORTS', 'AVAILABILITY_RULES', 'AVAILABILITY_EXCEPTIONS', 'APPOINTMENTS', 'MEETINGS', 'CLIENTS', 'INVOICES', 'CVS', 'RESUME_STUDIOS', 'PRODUCTS', 'ENROLLMENTS', 'STUDENTS', 'PORTFOLIOS'];

    /**
     * Singleton records: one row, provisioned by a seeder, edited in place and
     * never listed, created, deleted or exported. Only VIEW and UPDATE mean
     * anything — the other seven standard actions would be dead permissions,
     * the same reason EXPORT is omitted from {@see self::NO_EXPORT_MODULES} and
     * FORCE_DELETE from {@see self::MODULES_ACTIONS}.
     *
     * COMPANY_DATA moved here when the Company module shipped: the module has no
     * index, no store and no destroy route to guard.
     *
     * @var list<string>
     */
    private const array SINGLETON_MODULES = ['COMPANY_DATA'];

    /** @var list<string> */
    private const array SINGLETON_ACTIONS = ['VIEW', 'UPDATE'];

    /**
     * Modules with the same CRUD shape as {@see self::MODULES} but no export
     * endpoint yet, so EXPORT is omitted here (seeding it would only create a
     * dead permission, same reasoning as the FORCE_DELETE omission below).
     *
     * @var list<string>
     */
    private const array NO_EXPORT_MODULES = ['SERVICES'];

    /**
     * @var list<string>
     */
    private const array NO_EXPORT_ACTIONS = [
        'VIEW_ANY', 'VIEW', 'CREATE', 'UPDATE', 'DELETE', 'RESTORE', 'BULK_DELETE', 'BULK_RESTORE',
    ];

    /**
     * Full action set granted to every module (browse, view, write,
     * soft-delete + restore, bulk soft-delete + restore, and export).
     * FORCE_DELETE / BULK_FORCE_DELETE are omitted: no module implements hard
     * deletes yet, so seeding them would only create dead permissions.
     *
     * @var list<string>
     */
    private const array MODULES_ACTIONS = [
        'VIEW_ANY',
        'VIEW',
        'CREATE',
        'UPDATE',
        'DELETE',
        'RESTORE',
        'BULK_DELETE',
        'BULK_RESTORE',
        'EXPORT',
    ];

    /**
     * Modules the ADMIN role manages directly (beyond SUPER_ADMIN, who holds
     * everything). Availability + CRM + career + video tooling — day-to-day ops
     * without the full super-admin reach (users, roles, backups, etc.).
     *
     * @var list<string>
     */
    private const array ADMIN_MODULES = [
        'AVAILABILITY_RULES',
        'AVAILABILITY_EXCEPTIONS',
        'APPOINTMENTS',
        'MEETINGS',
        'CLIENTS',
        'INVOICES',
        'CVS',
        'RESUME_STUDIOS',
        'PRODUCTS',
        'ENROLLMENTS',
        'STUDENTS',
    ];

    /**
     * Pipeline tools the ADMIN role can run (no DB catalog CRUD shape).
     *
     * @var list<string>
     */
    private const array ADMIN_TOOL_MODULES = ['VIDEO_EXPORTS'];

    /**
     * CRM modules without an export endpoint yet (same shape as
     * {@see self::NO_EXPORT_MODULES}). Empty while every ADMIN CRM module
     * ships EXPORT.
     *
     * @var list<string>
     */
    private const array ADMIN_NO_EXPORT_MODULES = [];

    /**
     * Access-management permissions on the USERS module. Kept separate from the
     * standard CRUD set because granting access (attaching roles / direct
     * permission top-ups to a user) is far more sensitive than editing a name —
     * holding UPDATE_USERS must NOT imply the ability to elevate privileges.
     * Enforced at the route and, defence-in-depth, in SyncUserRolesHandler /
     * SetUserPermissionHandler (via the AssignableAccess invariant).
     *
     * @var list<string>
     */
    private const array USER_ACCESS_ACTIONS = ['ASSIGN_ROLES', 'ASSIGN_PERMISSIONS'];

    /**
     * Marking a reviewed AI package as published is a distinct, deliberate
     * action from UPDATE_SOCIAL_MEDIA (editing the draft) — kept as its own
     * permission for the same reason USER_ACCESS_ACTIONS stays separate from
     * standard CRUD.
     *
     * @var list<string>
     */
    private const array SOCIAL_MEDIA_PUBLISH_ACTIONS = ['PUBLISH'];

    /**
     * Marking a reviewed AI-generated Meta Ads campaign as published is a
     * distinct, deliberate action from UPDATE_CAMPAIGNS (editing the draft)
     * — same reasoning as {@see self::SOCIAL_MEDIA_PUBLISH_ACTIONS}.
     *
     * @var list<string>
     */
    private const array CAMPAIGNS_PUBLISH_ACTIONS = ['PUBLISH'];

    /**
     * Starting an AI studio pipeline is distinct from editing configs or matches.
     *
     * @var list<string>
     */
    private const array RESUME_STUDIOS_RUN_ACTIONS = ['RUN'];

    /**
     * Starting the AI content-generation pipeline for a product is a distinct,
     * metered action from editing the catalog row — same reasoning as
     * {@see self::RESUME_STUDIOS_RUN_ACTIONS}. It fans out into one LLM call
     * per topic, so holding UPDATE_PRODUCTS must NOT imply the ability to
     * spend that budget.
     *
     * @var list<string>
     */
    private const array PRODUCTS_GENERATE_ACTIONS = ['GENERATE'];

    /**
     * Pulling the generated course package (MD / PDF / ZIP) off the private
     * disk. Separate from EXPORT_PRODUCTS, which is the catalog spreadsheet.
     *
     * @var list<string>
     */
    private const array PRODUCTS_DOWNLOAD_ACTIONS = ['DOWNLOAD'];

    /**
     * Read-only modules (immutable audit trail): only browse / view / export.
     * No create/update/delete — the activity log is trimmed by cron, not the UI.
     *
     * @var list<string>
     */
    private const array READ_ONLY_MODULES = ['ACTIVITY_LOGS'];

    /** @var list<string> */
    private const array READ_ONLY_ACTIONS = ['VIEW_ANY', 'VIEW', 'EXPORT'];

    /**
     * Self-service modules: capabilities every signed-in person needs over their
     * OWN records, never over anyone else's. Ownership is enforced separately in
     * the handlers — these permissions only decide whether the feature exists
     * for a role at all.
     *
     * AUTH_SESSIONS (spec 001 FR-14) has no RESTORE counterpart on purpose: a
     * revoked session cannot be resurrected, so the usual DELETE/RESTORE and
     * BULK_DELETE/BULK_RESTORE pairing does not apply. BULK_DELETE guards
     * "sign out everywhere else".
     *
     * @var list<string>
     */
    private const array SELF_SERVICE_MODULES = ['AUTH_SESSIONS'];

    /** @var list<string> */
    private const array SELF_SERVICE_ACTIONS = ['VIEW_ANY', 'DELETE', 'BULK_DELETE'];

    /**
     * Roles that get the self-service set. GUEST is excluded: it has no session
     * of its own to manage.
     *
     * @var list<string>
     */
    private const array SELF_SERVICE_ROLES = ['ADMIN', 'MODERATOR', 'USER'];

    /**
     * Backups panel (spatie/laravel-backup): list, download an archive, run a
     * backup on demand, delete an archive. No update — a backup is immutable.
     *
     * @var list<string>
     */
    private const array BACKUP_ACTIONS = ['VIEW_ANY', 'DOWNLOAD', 'CREATE', 'DELETE'];

    /**
     * Video export pipeline (no DB catalog): view panel, create jobs, download results.
     *
     * @var list<string>
     */
    private const array VIDEO_EXPORT_ACTIONS = ['VIEW_ANY', 'CREATE', 'DOWNLOAD'];

    /**
     * Ops tooling dashboards (Horizon queue monitor, Telescope request/query
     * tracer). Binary view access only — mirrors the `viewHorizon` /
     * `viewTelescope` gates in HorizonServiceProvider / TelescopeServiceProvider.
     *
     * @var list<string>
     */
    private const array SYSTEM_MONITORING_MODULES = ['HORIZON', 'TELESCOPE'];

    /** @var list<string> */
    private const array SYSTEM_MONITORING_ACTIONS = ['VIEW'];

    /**
     * Foundation roles + permissions (web guard).
     *
     * Runs BEFORE UserSeeder so roles exist when users are assigned.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ensureRoles();
        $this->ensurePermissions($this->permissionNames());
        $this->grantAllToSuperAdmin();
        $this->grantModulesToAdmin();
        $this->grantSelfServiceModules();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureRoles(): void
    {
        foreach (self::ROLES as $role) {
            // SoftDeletes + unique(name, guard_name): a suspended role is invisible
            // to firstOrCreate, which then fails on insert. Restore instead of
            // aborting the seeder before grantAllToSuperAdmin runs.
            $model = Role::withTrashed()->firstOrCreate(
                ['name' => $role, 'guard_name' => self::GUARD],
                ['uuid' => (string) Str::uuid7()],
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }

    /**
     * All permission names to seed, de-duplicated.
     *
     * @return list<string>
     */
    private function permissionNames(): array
    {
        $names = [
            ...$this->matrix(self::MODULES, self::MODULES_ACTIONS),
            ...$this->matrix(self::NO_EXPORT_MODULES, self::NO_EXPORT_ACTIONS),
            ...$this->matrix(self::SINGLETON_MODULES, self::SINGLETON_ACTIONS),
            ...$this->matrix(['USERS'], self::USER_ACCESS_ACTIONS),
            ...$this->matrix(['SOCIAL_MEDIA'], self::SOCIAL_MEDIA_PUBLISH_ACTIONS),
            ...$this->matrix(['CAMPAIGNS'], self::CAMPAIGNS_PUBLISH_ACTIONS),
            ...$this->matrix(['RESUME_STUDIOS'], self::RESUME_STUDIOS_RUN_ACTIONS),
            ...$this->matrix(['PRODUCTS'], self::PRODUCTS_GENERATE_ACTIONS),
            ...$this->matrix(['PRODUCTS'], self::PRODUCTS_DOWNLOAD_ACTIONS),
            ...$this->matrix(self::READ_ONLY_MODULES, self::READ_ONLY_ACTIONS),
            ...$this->matrix(['BACKUPS'], self::BACKUP_ACTIONS),
            ...$this->matrix(['VIDEO_EXPORTS'], self::VIDEO_EXPORT_ACTIONS),
            ...$this->matrix(self::SYSTEM_MONITORING_MODULES, self::SYSTEM_MONITORING_ACTIONS),
            ...$this->matrix(self::SELF_SERVICE_MODULES, self::SELF_SERVICE_ACTIONS),
        ];

        return array_values(array_unique($names));
    }

    /**
     * Cartesian product of modules × actions → "{ACTION}_{MODULE}".
     *
     * @param  list<string>  $modules
     * @param  list<string>  $actions
     * @return list<string>
     */
    private function matrix(array $modules, array $actions): array
    {
        $names = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $names[] = "{$action}_{$module}";
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $names
     */
    private function ensurePermissions(array $names): void
    {
        foreach ($names as $name) {
            // Same SoftDeletes/unique footgun as roles — restore suspended rows
            // so syncPermissions can grant them (trashed perms are excluded from
            // Spatie runtime checks and from Permission::query()).
            $permission = Permission::withTrashed()->firstOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                ['uuid' => (string) Str::uuid7()],
            );

            if ($permission->trashed()) {
                $permission->restore();
            }
        }
    }

    /**
     * SUPER_ADMIN holds every active permission.
     */
    private function grantAllToSuperAdmin(): void
    {
        $role = Role::query()
            ->where('name', 'SUPER_ADMIN')
            ->where('guard_name', self::GUARD)
            ->first();

        $role?->syncPermissions(
            Permission::query()->where('guard_name', self::GUARD)->get(),
        );
    }

    /**
     * ADMIN manages the availability modules directly (full CRUD + export).
     * Additive and idempotent — never wipes grants the role accrues elsewhere.
     * Resume Studio also needs RUN (pipeline start) and Products GENERATE +
     * DOWNLOAD (content pipeline + package retrieval) beyond the standard set.
     */
    private function grantModulesToAdmin(): void
    {
        $names = [
            ...$this->matrix(self::ADMIN_MODULES, self::MODULES_ACTIONS),
            ...$this->matrix(self::ADMIN_NO_EXPORT_MODULES, self::NO_EXPORT_ACTIONS),
            ...$this->matrix(self::ADMIN_TOOL_MODULES, self::VIDEO_EXPORT_ACTIONS),
            ...$this->matrix(['RESUME_STUDIOS'], self::RESUME_STUDIOS_RUN_ACTIONS),
            ...$this->matrix(['PRODUCTS'], self::PRODUCTS_GENERATE_ACTIONS),
            ...$this->matrix(['PRODUCTS'], self::PRODUCTS_DOWNLOAD_ACTIONS),
        ];

        Role::query()
            ->where('name', 'ADMIN')
            ->where('guard_name', self::GUARD)
            ->first()
            ?->givePermissionTo($names);
    }

    /**
     * Every signed-in role manages its own sessions (spec 001 FR-14).
     * Additive and idempotent, like {@see self::grantModulesToAdmin()}.
     */
    private function grantSelfServiceModules(): void
    {
        $names = $this->matrix(self::SELF_SERVICE_MODULES, self::SELF_SERVICE_ACTIONS);

        Role::query()
            ->whereIn('name', self::SELF_SERVICE_ROLES)
            ->where('guard_name', self::GUARD)
            ->get()
            ->each(static fn (Role $role) => $role->givePermissionTo($names));
    }
}
