import { z } from 'zod';
import type { PermissionDetail, PermissionWritePayload } from '../types';

/**
 * The client-side mirror of `PermissionData::rules()`.
 *
 * The regex is the project's `{ACTION}_{MODULE}` naming convention (upper snake
 * case) — enforced here so the catalogue stays consistent and
 * code-referenceable, and enforced again on the server, which is authoritative.
 */

/** `PermissionData::rules()` → `regex:/^[A-Z][A-Z0-9_]*$/`. */
const PERMISSION_NAME_PATTERN = /^[A-Z][A-Z0-9_]*$/;

export const permissionFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Permission name is required.')
        .max(125, 'Permission name must be 125 characters or fewer.')
        .regex(
            PERMISSION_NAME_PATTERN,
            'Use upper snake case, e.g. “VIEW_ANY_ROLES”.',
        ),
});

export type PermissionFormValues = z.infer<typeof permissionFormSchema>;

export function emptyPermissionFormValues(): PermissionFormValues {
    return { name: '' };
}

export function toPermissionFormValues(
    permission: PermissionDetail,
): PermissionFormValues {
    return { name: permission.name };
}

export function toPermissionWritePayload(
    values: PermissionFormValues,
): PermissionWritePayload {
    return { name: values.name.trim().toUpperCase() };
}
