import type { BillingUnit, ProductStatus, ProductType } from '../types';

/**
 * Row-derived display values shared by the table, the detail dialog and the
 * delete confirmations, so "how a product is labelled" is decided once.
 */

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

const TYPE_LABELS: Record<ProductType, string> = {
    COURSE: 'Course',
    VIDEO_COURSE: 'Video course',
    WORKSHOP: 'Workshop',
    MENTORING: 'Mentoring',
};

export function productTypeLabel(type: ProductType): string {
    return TYPE_LABELS[type];
}

const STATUS_LABELS: Record<ProductStatus, string> = {
    DRAFT: 'Draft',
    PUBLISHED: 'Published',
    ARCHIVED: 'Archived',
};

export function productStatusLabel(status: ProductStatus): string {
    return STATUS_LABELS[status];
}

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

/** A published product stands out — it is the only kind an invoice can bill. */
const STATUS_VARIANTS: Record<ProductStatus, BadgeVariant> = {
    PUBLISHED: 'default',
    ARCHIVED: 'secondary',
    DRAFT: 'outline',
};

export function productStatusVariant(status: ProductStatus): BadgeVariant {
    return STATUS_VARIANTS[status];
}

const UNIT_LABELS: Record<BillingUnit, string> = {
    UNIT: 'per unit',
    HOUR: 'per hour',
    SESSION: 'per session',
    DAY: 'per day',
    MONTH: 'per month',
};

export function billingUnitLabel(unit: BillingUnit): string {
    return UNIT_LABELS[unit];
}

/** Short form for a table cell: `52.00 EUR/hour`. */
export function formatUnitPrice(
    price: number,
    currency: string,
    unit: BillingUnit,
): string {
    const amount = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(price);

    return unit === 'UNIT'
        ? `${amount} ${currency}`
        : `${amount} ${currency}/${UNIT_LABELS[unit].replace('per ', '')}`;
}
