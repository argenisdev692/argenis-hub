import { z } from 'zod';
import type {
    BillingUnit,
    Product,
    ProductStatus,
    ProductType,
    ProductWritePayload,
} from '../types';

/**
 * The client-side mirror of `StoreProductData::rules()`.
 *
 * Every limit here exists on the server too, and the server is the one that
 * counts — this schema buys immediate feedback, not safety. `TextField` always
 * hands back a string, so numeric and optional fields are modelled as (possibly
 * empty) strings and coerced at the wire boundary in `toWritePayload()`.
 */

const CURRENCY_PATTERN = /^[A-Za-z]{3}$/;
const DECIMAL_PATTERN = /^\d+([.,]\d{1,2})?$/;
const INTEGER_PATTERN = /^\d+$/;

/** The literal values each enum allows — one source for the schema + guards. */
export const PRODUCT_TYPE_VALUES = [
    'COURSE',
    'VIDEO_COURSE',
    'WORKSHOP',
    'MENTORING',
] as const satisfies readonly ProductType[];

export const PRODUCT_STATUS_VALUES = [
    'DRAFT',
    'PUBLISHED',
    'ARCHIVED',
] as const satisfies readonly ProductStatus[];

export const BILLING_UNIT_VALUES = [
    'UNIT',
    'HOUR',
    'SESSION',
    'DAY',
    'MONTH',
] as const satisfies readonly BillingUnit[];

export const PRODUCT_LEVEL_VALUES = [
    'beginner',
    'intermediate',
    'advanced',
] as const;

export const PRODUCT_MODALITY_VALUES = [
    'online',
    'onsite',
    'hybrid',
] as const;

/** Narrows the reka `Select` model value (typed `unknown`) back to the enum. */
export function isProductType(value: unknown): value is ProductType {
    return (
        typeof value === 'string' &&
        (PRODUCT_TYPE_VALUES as readonly string[]).includes(value)
    );
}

export function isProductStatus(value: unknown): value is ProductStatus {
    return (
        typeof value === 'string' &&
        (PRODUCT_STATUS_VALUES as readonly string[]).includes(value)
    );
}

export function isBillingUnit(value: unknown): value is BillingUnit {
    return (
        typeof value === 'string' &&
        (BILLING_UNIT_VALUES as readonly string[]).includes(value)
    );
}

export const productFormSchema = z
    .object({
        type: z.enum(PRODUCT_TYPE_VALUES),
        title: z
            .string()
            .trim()
            .min(1, 'Title is required.')
            .max(255, 'Title must be 255 characters or fewer.'),
        description: z
            .string()
            .trim()
            .max(5000, 'Description must be 5000 characters or fewer.'),
        price: z
            .string()
            .trim()
            .min(1, 'Price is required.')
            .regex(DECIMAL_PATTERN, 'Enter an amount like 52 or 52.00.'),
        currency: z
            .string()
            .trim()
            .regex(CURRENCY_PATTERN, 'Use the 3-letter ISO code (e.g. “EUR”).'),
        default_unit: z.enum(BILLING_UNIT_VALUES),
        status: z.enum(PRODUCT_STATUS_VALUES),
        level: z.string().trim().max(32, 'Level must be 32 characters or fewer.'),
        language: z
            .string()
            .trim()
            .min(1, 'Language is required.')
            .max(8, 'Language must be 8 characters or fewer.'),
        client_uuid: z.string().trim(),
        start_date: z.string().trim(),
        end_date: z.string().trim(),
        total_hours: z
            .string()
            .trim()
            .refine(
                (value) => value === '' || DECIMAL_PATTERN.test(value),
                'Enter a number of hours like 25 or 3.5.',
            ),
        total_sessions: z
            .string()
            .trim()
            .refine(
                (value) => value === '' || INTEGER_PATTERN.test(value),
                'Enter a whole number of sessions.',
            ),
        modality: z.string().trim().max(16, 'Modality must be 16 characters or fewer.'),
        notes: z
            .string()
            .trim()
            .max(5000, 'Notes must be 5000 characters or fewer.'),
    })
    // Mirrors `after_or_equal:start_date`, reported on the field the user can fix.
    .refine(
        (values) =>
            values.start_date === '' ||
            values.end_date === '' ||
            values.end_date >= values.start_date,
        {
            message: 'End date cannot be before the start date.',
            path: ['end_date'],
        },
    );

export type ProductFormValues = z.infer<typeof productFormSchema>;

export function emptyProductFormValues(): ProductFormValues {
    return {
        type: 'COURSE',
        title: '',
        description: '',
        price: '',
        currency: 'EUR',
        default_unit: 'HOUR',
        status: 'DRAFT',
        level: 'beginner',
        language: 'es',
        client_uuid: '',
        start_date: '',
        end_date: '',
        total_hours: '',
        total_sessions: '',
        modality: '',
        notes: '',
    };
}

export function toProductFormValues(product: Product): ProductFormValues {
    return {
        type: product.type,
        title: product.title,
        description: product.description ?? '',
        price: String(product.price),
        currency: product.currency,
        default_unit: product.default_unit,
        status: product.status,
        level: product.level,
        language: product.language,
        client_uuid: product.client_uuid ?? '',
        start_date: product.start_date ?? '',
        end_date: product.end_date ?? '',
        total_hours: product.total_hours === null ? '' : String(product.total_hours),
        total_sessions:
            product.total_sessions === null ? '' : String(product.total_sessions),
        modality: product.modality ?? '',
        notes: product.notes ?? '',
    };
}

/** Empty optional strings become `null`; the rest are trimmed at the boundary. */
function nullable(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

/** `"3,5"` and `"3.5"` both mean 3.5 — the comma is a real habit in es/pt input. */
function decimal(value: string): number | null {
    const trimmed = value.trim().replace(',', '.');

    return trimmed === '' ? null : Number(trimmed);
}

function integer(value: string): number | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : Number.parseInt(trimmed, 10);
}

/**
 * Projects the form onto the exact body the endpoint accepts.
 *
 * Spelled out field by field rather than looped, because the return type is the
 * generated write payload: a missing or misnamed key is a compile error here
 * instead of a silently dropped column at runtime.
 */
export function toWritePayload(
    values: ProductFormValues,
): ProductWritePayload {
    return {
        type: values.type,
        title: values.title.trim(),
        description: nullable(values.description),
        price: decimal(values.price) ?? 0,
        currency: values.currency.trim().toUpperCase(),
        default_unit: values.default_unit,
        status: values.status,
        level: values.level.trim(),
        language: values.language.trim(),
        client_uuid: nullable(values.client_uuid),
        start_date: nullable(values.start_date),
        end_date: nullable(values.end_date),
        total_hours: decimal(values.total_hours),
        total_sessions: integer(values.total_sessions),
        modality: nullable(values.modality),
        notes: nullable(values.notes),
    };
}
