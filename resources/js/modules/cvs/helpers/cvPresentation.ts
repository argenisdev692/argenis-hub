import {
    CircleSlashIcon,
    FileCodeIcon,
    FileTextIcon,
    LayersIcon,
    TagIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type { Cv, CvFileType, CvNiche } from '../types';

/**
 * Row-derived display values shared by the table, the dialog, the detail page
 * and the confirmation modals, so "how a CV reads in the UI" is decided once.
 */

type Presentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

const ACTIVE: Presentation = {
    label: 'Active',
    variant: 'default',
    icon: FileTextIcon,
};

/**
 * "Suspended", not "Deleted", because that is the word the backend uses for
 * this state everywhere the operator can see it: the filter value is
 * `status=suspended`, and `destroy()` flashes "CV suspended.".
 */
const SUSPENDED: Presentation = {
    label: 'Suspended',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

/**
 * A CV has no lifecycle column — soft deletion is the whole of its status, so
 * `deleted_at` is the only input.
 */
export function cvStatusPresentation(deletedAt: string | null): Presentation {
    return deletedAt === null ? ACTIVE : SUSPENDED;
}

const NICHES: Record<CvNiche, Presentation> = {
    fullstack: {
        label: 'Full-stack',
        variant: 'secondary',
        icon: LayersIcon,
    },
    other: {
        label: 'Other',
        variant: 'outline',
        icon: TagIcon,
    },
};

export function cvNichePresentation(niche: CvNiche): Presentation {
    return NICHES[niche];
}

/** The niche facet as the filter select offers it, "Any niche" excluded. */
export const CV_NICHES: readonly CvNiche[] = ['fullstack', 'other'];

const FILE_TYPES: Record<CvFileType, { label: string; icon: LucideIcon }> = {
    pdf: { label: 'PDF', icon: FileTextIcon },
    md: { label: 'Markdown', icon: FileCodeIcon },
};

export function cvFileTypePresentation(fileType: CvFileType): {
    label: string;
    icon: LucideIcon;
} {
    return FILE_TYPES[fileType];
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

/**
 * The label every confirmation modal, toast and `aria-label` uses for a single
 * row. Falls back to the original filename, then the uuid, so a CV saved with a
 * blank-looking title is still identifiable in a "suspend this?" prompt.
 */
export function cvLabel(cv: Cv): string {
    return cv.title.trim() || cv.original_filename || cv.uuid;
}

/** The owner's display name, or an em dash once the user row is gone. */
export function cvOwnerName(cv: Cv): string {
    return cv.owner_name?.trim() || '—';
}
