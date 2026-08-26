import { z } from 'zod';
import type { Service, ServiceWritePayload } from '../types';

/**
 * The client-side mirror of `StoreServiceRequest` / `UpdateServiceRequest`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety.
 *
 * ## Why `sort_order` is a string in the form
 *
 * `TextField` always hands back a string (an `<input>` yields one), so the
 * form models every field as a string — including the numeric one — and
 * `toWritePayload()` performs the single string-to-number conversion at the
 * wire boundary, which is also the only place that conversion is meaningful.
 */

export const serviceFormSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Name is required.')
        .max(255, 'Name must be 255 characters or fewer.'),
    slug: z
        .string()
        .trim()
        .min(1, 'Slug is required.')
        .max(100, 'Slug must be 100 characters or fewer.')
        .regex(
            /^[a-z0-9_]+$/,
            'Use lowercase letters, digits and underscores only.',
        ),
    description: z
        .string()
        .trim()
        .max(500, 'Description must be 500 characters or fewer.'),
    is_active: z.boolean(),
    sort_order: z
        .string()
        .trim()
        .regex(/^\d+$/, 'Enter a whole number.')
        .refine((value) => Number(value) <= 2147483647, {
            message: 'Sort order is too large.',
        }),
});

export type ServiceFormValues = z.infer<typeof serviceFormSchema>;

export function emptyServiceFormValues(): ServiceFormValues {
    return {
        name: '',
        slug: '',
        description: '',
        is_active: true,
        sort_order: '0',
    };
}

export function toServiceFormValues(service: Service): ServiceFormValues {
    return {
        name: service.name,
        slug: service.slug,
        description: service.description ?? '',
        is_active: service.is_active,
        sort_order: String(service.sort_order),
    };
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is
 * the generated write payload: a missing or misnamed key is a compile error
 * here instead of a silently dropped column at runtime.
 */
export function toWritePayload(values: ServiceFormValues): ServiceWritePayload {
    return {
        name: values.name.trim(),
        slug: values.slug.trim(),
        description: values.description.trim() || null,
        is_active: values.is_active,
        sort_order: Number(values.sort_order),
    };
}
