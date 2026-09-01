import { z } from 'zod';
import type { RoleDetail, RoleWritePayload } from '../types';

/**
 * The client-side mirror of `RoleData::rules()`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. Uniqueness and the
 * "permission must exist in the catalogue" check stay server-side: neither can
 * be decided from the browser without lying about a race.
 */

/** `RoleData::rules()` → `regex:/^[A-Za-z0-9 _-]+$/`. */
const ROLE_NAME_PATTERN = /^[A-Za-z0-9 _-]+$/;

export const roleFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Role name is required.')
        .max(125, 'Role name must be 125 characters or fewer.')
        .regex(
            ROLE_NAME_PATTERN,
            'Use letters, numbers, spaces, hyphens and underscores only.',
        ),
    /**
     * The full set of permission names the role should hold afterwards — the
     * backend *syncs* rather than merges, so an empty array revokes every
     * grant. That is a legitimate state, hence no `min(1)`.
     */
    permissions: z.array(z.string()),
});

export type RoleFormValues = z.infer<typeof roleFormSchema>;

export function emptyRoleFormValues(): RoleFormValues {
    return { name: '', permissions: [] };
}

export function toRoleFormValues(role: RoleDetail): RoleFormValues {
    return {
        name: role.name,
        permissions: role.permissions.map((permission) => permission.name),
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than spread, because the return type is the
 * generated write payload: a missing or misnamed key is a compile error here
 * instead of a silently dropped column at runtime.
 */
export function toRoleWritePayload(values: RoleFormValues): RoleWritePayload {
    return {
        name: values.name.trim(),
        permissions: [...values.permissions],
    };
}
