import { Banknote, CalendarClock, Send, UserPlus, Users } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BrandTone } from '@/modules/marketing/types';
import type { ActivityKind } from '../types';

type ActivityPresentation = {
    readonly icon: LucideIcon;
    readonly tone: BrandTone;
};

/**
 * Activity kind → glyph and brand hue.
 *
 * The icon is what distinguishes one entry type from another; the tone only
 * reinforces it, so the feed stays readable in greyscale.
 */
const ACTIVITY_PRESENTATION: Readonly<
    Record<ActivityKind, ActivityPresentation>
> = {
    invoice_paid: { icon: Banknote, tone: 'gold' },
    candidate_matched: { icon: Users, tone: 'cyan' },
    client_added: { icon: UserPlus, tone: 'purple' },
    meeting_booked: { icon: CalendarClock, tone: 'indigo' },
    campaign_sent: { icon: Send, tone: 'magenta' },
} as const;

export function activityPresentation(kind: ActivityKind): ActivityPresentation {
    return ACTIVITY_PRESENTATION[kind];
}
