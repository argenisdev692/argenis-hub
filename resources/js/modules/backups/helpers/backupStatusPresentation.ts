import { CircleCheckIcon, CircleDashedIcon, CircleXIcon } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';

type StatusPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

/**
 * One lookup for how a backup `status` reads in the UI — its badge tone, its
 * human label and its glyph. Shared by the list badge and the detail dialog so
 * the two never drift.
 *
 * `status` crosses the wire as a plain string (`BackupData::$status`); an
 * unknown value falls back to a neutral chip rather than being hidden, because
 * "a run exists in a state we don't recognise" is still information.
 */
const PRESENTATION: Record<string, StatusPresentation> = {
    running: { label: 'Running', variant: 'secondary', icon: CircleDashedIcon },
    completed: {
        label: 'Completed',
        variant: 'default',
        icon: CircleCheckIcon,
    },
    failed: { label: 'Failed', variant: 'destructive', icon: CircleXIcon },
};

const FALLBACK: StatusPresentation = {
    label: 'Unknown',
    variant: 'outline',
    icon: CircleDashedIcon,
};

export function backupStatusPresentation(status: string): StatusPresentation {
    return PRESENTATION[status] ?? FALLBACK;
}
