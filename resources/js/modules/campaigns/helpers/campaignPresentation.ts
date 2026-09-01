import {
    CalendarClockIcon,
    CircleSlashIcon,
    FileEditIcon,
    GlobeIcon,
    LoaderIcon,
    SparklesIcon,
    TriangleAlertIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type { CampaignListItem } from '../types';

/**
 * Row-derived display values shared by the table, the review page and the
 * confirmation modals, so "how a campaign reads in the UI" is decided once.
 *
 * Deliberately not imported from `modules/social-media/helpers/
 * socialMediaPresentation` even though the two overlap: the enum vocabularies
 * differ (Campaigns adds `retention`, `loyalty`, the Meta ad formats and the
 * network axis, and drops `viral`/`community`), and a shared lookup would have
 * to grow a per-module branch to serve both — coupling two modules to save a
 * table.
 */

type StatusPresentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

/**
 * `status` crosses the wire as a plain string. An unrecognised value gets a
 * neutral chip rather than being hidden — "this row is in a state we don't know
 * about" is still information the operator needs.
 */
const PRESENTATION: Record<string, StatusPresentation> = {
    draft: { label: 'Draft', variant: 'secondary', icon: FileEditIcon },
    generating: { label: 'Generating', variant: 'outline', icon: LoaderIcon },
    ready: { label: 'Ready', variant: 'default', icon: SparklesIcon },
    needs_review: {
        label: 'Needs review',
        variant: 'outline',
        icon: TriangleAlertIcon,
    },
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
 * backend too: the repository turns `status=suspended` into `onlyTrashed()`
 * rather than a `where`, and a trashed row's stored status is stale information
 * the list has no reason to keep showing.
 */
const SUSPENDED: StatusPresentation = {
    label: 'Suspended',
    variant: 'destructive',
    icon: CircleSlashIcon,
};

export function campaignStatusPresentation(
    status: string,
    deletedAt: string | null = null,
): StatusPresentation {
    if (deletedAt !== null) {
        return SUSPENDED;
    }

    return PRESENTATION[status] ?? UNKNOWN;
}

/**
 * Human labels for the generation enums.
 *
 * Spelled out rather than derived from the value, because `tofu` reads as
 * "Top of funnel" and `lead_form` as "Lead form" — no amount of `startCase`
 * gets to either.
 */
const BUSINESS_GOAL_LABELS: Record<string, string> = {
    awareness: 'Awareness',
    engagement: 'Engagement',
    leads: 'Lead generation',
    sales: 'Sales',
    retention: 'Retention',
};

const BRAND_VOICE_LABELS: Record<string, string> = {
    professional: 'Professional',
    conversational: 'Conversational',
    trendy: 'Trendy',
    inspirational: 'Inspirational',
    humorous: 'Humorous',
};

const FUNNEL_STAGE_LABELS: Record<string, string> = {
    tofu: 'Top of funnel',
    mofu: 'Middle of funnel',
    bofu: 'Bottom of funnel',
    loyalty: 'Loyalty',
};

const PLATFORM_LABELS: Record<string, string> = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    both: 'Facebook + Instagram',
};

const AD_FORMAT_LABELS: Record<string, string> = {
    feed: 'Feed',
    story: 'Story',
    reel: 'Reel',
    carousel: 'Carousel',
    lead_form: 'Lead form',
};

/**
 * Mirrors `CampaignLanguage::label()` verbatim, including the parenthetical
 * dialect notes — those are the point of the enum, not decoration: `es` is
 * neutral LatAm Spanish and `pt-PT` is explicitly not Brazilian Portuguese.
 */
const LANGUAGE_LABELS: Record<string, string> = {
    es: 'Español (LatAm neutro)',
    en: 'English',
    'pt-PT': 'Português (Portugal)',
};

/** Falls back to the raw value so an enum added server-side still renders. */
function label(lookup: Record<string, string>, value: string): string {
    return lookup[value] ?? value;
}

export function campaignBusinessGoalLabel(value: string): string {
    return label(BUSINESS_GOAL_LABELS, value);
}

export function campaignBrandVoiceLabel(value: string): string {
    return label(BRAND_VOICE_LABELS, value);
}

export function campaignFunnelStageLabel(value: string): string {
    return label(FUNNEL_STAGE_LABELS, value);
}

export function campaignPlatformLabel(value: string): string {
    return label(PLATFORM_LABELS, value);
}

export function campaignAdFormatLabel(value: string): string {
    return label(AD_FORMAT_LABELS, value);
}

export function campaignLanguageLabel(value: string): string {
    return label(LANGUAGE_LABELS, value);
}

/**
 * `success_probability_label` is free text from the evaluator (`high`,
 * `medium`, …), so it is title-cased rather than looked up — a value the
 * evaluator invents tomorrow still reads correctly.
 */
export function successProbabilityLabel(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ');
}

/** `{ value, label }` pairs for the wizard's selects, from the same tables. */
export function optionsFrom(
    lookup: Record<string, string>,
): { value: string; label: string }[] {
    return Object.entries(lookup).map(([value, text]) => ({
        value,
        label: text,
    }));
}

export const CAMPAIGN_BUSINESS_GOAL_OPTIONS = optionsFrom(BUSINESS_GOAL_LABELS);
export const CAMPAIGN_BRAND_VOICE_OPTIONS = optionsFrom(BRAND_VOICE_LABELS);
export const CAMPAIGN_FUNNEL_STAGE_OPTIONS = optionsFrom(FUNNEL_STAGE_LABELS);
export const CAMPAIGN_PLATFORM_OPTIONS = optionsFrom(PLATFORM_LABELS);
export const CAMPAIGN_AD_FORMAT_OPTIONS = optionsFrom(AD_FORMAT_LABELS);
export const CAMPAIGN_LANGUAGE_OPTIONS = optionsFrom(LANGUAGE_LABELS);

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

/** The creator's display name, or an em dash once the user row is gone. */
export function campaignCreatorName(
    campaign: Pick<CampaignListItem, 'creator'>,
): string {
    if (!campaign.creator) {
        return '—';
    }

    const name = `${campaign.creator.first_name ?? ''} ${campaign.creator.last_name ?? ''}`;

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
