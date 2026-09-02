import { CircleSlashIcon, FolderIcon } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type { BlogCategory, BlogCategoryDetail } from '../types';

/**
 * Row-derived display values shared by the table, the dialog and the
 * confirmation modals, so "how a category reads in the UI" is decided once.
 */

type StatusPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

const ACTIVE: StatusPresentation = {
    label: 'Active',
    variant: 'default',
    icon: FolderIcon,
};

/**
 * "Suspended", not "Deleted", because that is the word the backend uses for
 * this state everywhere the operator can see it: the filter value is
 * `status=suspended`, and `destroy()` flashes "Blog category suspended.".
 */
const SUSPENDED: StatusPresentation = {
    label: 'Suspended',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

/**
 * A category has no lifecycle column — soft deletion is the whole of its
 * status, so `deleted_at` is the only input.
 */
export function blogCategoryStatusPresentation(
    deletedAt: string | null,
): StatusPresentation {
    return deletedAt === null ? ACTIVE : SUSPENDED;
}

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

/** The author's display name, or an em dash once the user row is gone. */
export function blogCategoryAuthorName(category: BlogCategory): string {
    if (!category.user) {
        return '—';
    }

    const name = `${category.user.first_name ?? ''} ${category.user.last_name ?? ''}`;

    return name.trim() || '—';
}

/**
 * The label every confirmation modal, toast and `aria-label` uses for a single
 * row. Falls back to the uuid rather than an empty string so a nameless row is
 * still identifiable in a "delete this?" prompt.
 */
export function blogCategoryLabel(
    category: BlogCategory | BlogCategoryDetail,
): string {
    return category.blog_category_name?.trim() || category.uuid;
}
