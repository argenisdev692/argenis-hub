import {
    ActivityIcon,
    FilePenLineIcon,
    FilePlus2Icon,
    RotateCcwIcon,
    Trash2Icon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';

type EventPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
    /** Chip background + text, for the dashboard feed's icon medallion. */
    chipClass: string;
    /** Tone dot, for the compact contexts that only have room for one. */
    dotClass: string;
};

/**
 * One lookup for how an activity `event` reads in the UI — its badge tone, its
 * human label, its glyph and the chip/dot classes the dashboard feed needs.
 * Shared by the list, the detail page and the dashboard panel so the three
 * never drift.
 *
 * `event` is nullable on the wire (a bare `activity()->log()` call sets none);
 * an unknown or missing value falls back to a neutral "Activity" chip rather
 * than being hidden, because "something happened and we don't know what" is
 * still information on an audit trail.
 */
const PRESENTATION: Record<string, EventPresentation> = {
    created: {
        label: 'Created',
        variant: 'default',
        icon: FilePlus2Icon,
        chipClass: 'bg-primary/10 text-primary',
        dotClass: 'bg-primary',
    },
    updated: {
        label: 'Updated',
        variant: 'secondary',
        icon: FilePenLineIcon,
        chipClass: 'bg-muted text-muted-foreground',
        dotClass: 'bg-muted-foreground',
    },
    deleted: {
        label: 'Deleted',
        variant: 'destructive',
        icon: Trash2Icon,
        chipClass: 'bg-destructive/10 text-destructive',
        dotClass: 'bg-destructive',
    },
    restored: {
        label: 'Restored',
        variant: 'outline',
        icon: RotateCcwIcon,
        chipClass: 'bg-muted text-muted-foreground',
        dotClass: 'bg-muted-foreground',
    },
};

const FALLBACK: EventPresentation = {
    label: 'Activity',
    variant: 'outline',
    icon: ActivityIcon,
    chipClass: 'bg-muted text-muted-foreground',
    dotClass: 'bg-muted-foreground',
};

export function activityLogEventPresentation(
    event: string | null,
): EventPresentation {
    return (event && PRESENTATION[event]) || FALLBACK;
}
