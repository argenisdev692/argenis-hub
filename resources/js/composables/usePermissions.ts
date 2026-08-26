import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

export type UsePermissionsReturn = {
    /** Every permission name granted to the current user. */
    permissions: ComputedRef<readonly string[]>;
    can: (permission: string) => boolean;
    canAny: (permissions: readonly string[]) => boolean;
    canAll: (permissions: readonly string[]) => boolean;
};

/**
 * Reads the permission list shared on every Inertia response.
 *
 * Backed by a `Set` rather than `Array.includes` because the sidebar and the
 * row-action columns ask this question once per item per render; a linear scan
 * over a few dozen permission names is cheap individually and stops being cheap
 * inside a `v-for` over a full page of rows.
 *
 * The guard is presentational only. Every route this hides is independently
 * protected by `permission:*` middleware — see `Modules\Company` web routes.
 */
export function usePermissions(): UsePermissionsReturn {
    const page = usePage();

    const permissions = computed<readonly string[]>(
        () => page.props.auth?.permissions ?? [],
    );

    const granted = computed(() => new Set(permissions.value));

    function can(permission: string): boolean {
        return granted.value.has(permission);
    }

    function canAny(required: readonly string[]): boolean {
        return required.length === 0 || required.some(can);
    }

    function canAll(required: readonly string[]): boolean {
        return required.every(can);
    }

    return { permissions, can, canAny, canAll };
}
