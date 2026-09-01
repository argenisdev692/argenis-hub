/**
 * Row-derived display values shared by both tables, the detail pages and the
 * confirmations, so "how a role is labelled" is decided once.
 */

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

/** ISO8601 → "3 Jun 2026", or `null` when there is no timestamp. */
export function formatDate(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

/** ISO8601 → "3 Jun 2026, 14:05", or `null` when there is no timestamp. */
export function formatDateTime(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

/**
 * The soft-delete axis reads as "suspended", not "deleted".
 *
 * That is the backend's own vocabulary (`RoleController` flashes "Role
 * suspended.", the filter DTO validates `in:active,suspended`) and it is the
 * accurate one: a trashed role is excluded from every runtime authorization
 * check by the global scope, which is a suspension, not a deletion.
 */
export function suspensionLabel(deletedAt: string | null): string {
    return deletedAt ? 'Suspended' : 'Active';
}

export function suspensionVariant(deletedAt: string | null): BadgeVariant {
    return deletedAt ? 'destructive' : 'default';
}

/**
 * System roles seeded by `RolePermissionSeeder` and treated as an invariant by
 * `Modules\Authorization\Domain\SystemRoles` — they can never be renamed or
 * deleted through the CRUD surface.
 *
 * The page reads the authoritative list from the `protectedRoles` Inertia prop
 * that `RoleController::index` shares; this predicate just applies it, so the
 * UI and the domain rule cannot drift.
 */
export function isProtectedRole(
    name: string,
    protectedRoles: readonly string[],
): boolean {
    return protectedRoles.includes(name);
}
