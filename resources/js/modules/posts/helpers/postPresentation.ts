import {
    CalendarClockIcon,
    CircleSlashIcon,
    FileEditIcon,
    GlobeIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type { PostListItem } from '../types';

/**
 * Row-derived display values shared by the table, the form pages and the
 * confirmation modals, so "how a post reads in the UI" is decided once.
 */

type StatusPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

/**
 * `post_status` crosses the wire as a plain string. An unrecognised value gets
 * a neutral chip rather than being hidden — "this row is in a state we don't
 * know about" is still information the operator needs.
 */
const PRESENTATION: Record<string, StatusPresentation> = {
    draft: { label: 'Draft', variant: 'secondary', icon: FileEditIcon },
    published: { label: 'Published', variant: 'default', icon: GlobeIcon },
    scheduled: {
        label: 'Scheduled',
        variant: 'outline',
        icon: CalendarClockIcon,
    },
};

const UNKNOWN: StatusPresentation = {
    label: 'Unknown',
    variant: 'outline',
    icon: CircleSlashIcon,
};

/**
 * Soft deletion outranks the lifecycle value, because it outranks it on the
 * backend too: `PostFilterData` folds `suspended` into the same axis as the
 * three `post_status` values, and a trashed row's stored status is stale
 * information the list has no reason to keep showing.
 */
const SUSPENDED: StatusPresentation = {
    label: 'Suspended',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

export function postStatusPresentation(
    status: string,
    deletedAt: string | null = null,
): StatusPresentation {
    if (deletedAt !== null) {
        return SUSPENDED;
    }

    return PRESENTATION[status] ?? UNKNOWN;
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
export function postAuthorName(post: PostListItem): string {
    if (!post.user) {
        return '—';
    }

    const name = `${post.user.first_name ?? ''} ${post.user.last_name ?? ''}`;

    return name.trim() || '—';
}

export type ScoreTone = 'good' | 'fair' | 'poor' | 'unknown';

/**
 * How a 0–100 quality score reads.
 *
 * `higherIsBetter: false` flips the scale for `ai_detection_risk`, the one
 * score in the set where 90 is bad news. A tone name rather than a class
 * string, so the same judgement can drive a numeral, a meter fill and a border
 * without three near-identical lookups drifting apart.
 */
export function scoreTone(
    score: number | null,
    higherIsBetter = true,
): ScoreTone {
    if (score === null) {
        return 'unknown';
    }

    const normalized = higherIsBetter ? score : 100 - score;

    if (normalized >= 80) {
        return 'good';
    }

    return normalized >= 60 ? 'fair' : 'poor';
}

const TEXT_CLASS: Record<ScoreTone, string> = {
    good: 'text-success',
    fair: 'text-warning',
    poor: 'text-destructive',
    unknown: 'text-muted-foreground',
};

const BAR_CLASS: Record<ScoreTone, string> = {
    good: 'bg-success',
    fair: 'bg-warning',
    poor: 'bg-destructive',
    unknown: 'bg-muted-foreground',
};

export function scoreTextClass(tone: ScoreTone): string {
    return TEXT_CLASS[tone];
}

export function scoreBarClass(tone: ScoreTone): string {
    return BAR_CLASS[tone];
}
