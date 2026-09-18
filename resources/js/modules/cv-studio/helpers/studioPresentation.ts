import type {
    StudioBand,
    StudioPostingStage,
    StudioRemoteScope,
} from '../types';

/** Formats an ISO date string as `Mar 4, 2026`, empty string when absent. */
export function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

type Option<TValue extends string> = { value: TValue; label: string };

/**
 * Tones resolve to semantic utilities bound to `globals.css` tokens
 * (`--success`, `--primary`, `--warning`, …) so they flip with `.dark` —
 * never a Tailwind palette name at the call site.
 */
const NEUTRAL_TONE = 'bg-muted text-muted-foreground';

const BAND_PRESENTATION: Record<StudioBand, { label: string; tone: string }> = {
    strong: { label: 'Strong', tone: 'bg-success/15 text-success' },
    good: { label: 'Good', tone: 'bg-primary/15 text-primary' },
    apply: { label: 'Apply', tone: 'bg-warning/15 text-warning' },
    consider: { label: 'Consider', tone: 'bg-accent text-accent-foreground' },
    skip: { label: 'Skip', tone: NEUTRAL_TONE },
};

function isBand(value: string): value is StudioBand {
    return value in BAND_PRESENTATION;
}

/** Human label for a score band; unknown values pass through unchanged. */
export function bandLabel(band: string | null): string {
    if (band === null) {
        return 'Unscored';
    }

    return isBand(band) ? BAND_PRESENTATION[band].label : band;
}

export function bandTone(band: string | null): string {
    return band !== null && isBand(band)
        ? BAND_PRESENTATION[band].tone
        : NEUTRAL_TONE;
}

/** Single source for the scope filter AND the scope column label. */
export const REMOTE_SCOPE_OPTIONS: Option<StudioRemoteScope>[] = [
    { value: 'remote_global', label: 'Remote · Global' },
    { value: 'remote_eu', label: 'Remote · EU' },
    { value: 'remote_pt_es', label: 'Remote · PT/ES' },
    { value: 'remote_unclear', label: 'Remote · Unclear' },
    { value: 'hybrid_local', label: 'Hybrid / Local' },
];

/** Remote-scope label for filters and badges. */
export function remoteScopeLabel(scope: string | null): string {
    return (
        REMOTE_SCOPE_OPTIONS.find((option) => option.value === scope)?.label ??
        'Unknown scope'
    );
}

/** Pipeline stages in the order a posting moves through them. */
export const POSTING_STAGE_OPTIONS: Option<StudioPostingStage>[] = [
    { value: 'new', label: 'New' },
    { value: 'saved', label: 'Saved' },
    { value: 'applied', label: 'Applied' },
    { value: 'dismissed', label: 'Dismissed' },
    { value: 'skipped', label: 'Skipped' },
];

const STAGE_TONES: Partial<Record<string, string>> = {
    new: 'bg-primary/15 text-primary',
    saved: 'bg-warning/15 text-warning',
    applied: 'bg-success/15 text-success',
};

/** Label for a posting's `status` column, including the manual-reference state. */
export function postingStageLabel(status: string): string {
    if (status === 'reference') {
        return 'Open manually';
    }

    return (
        POSTING_STAGE_OPTIONS.find((option) => option.value === status)
            ?.label ?? status
    );
}

export function postingStageTone(status: string): string {
    return STAGE_TONES[status] ?? NEUTRAL_TONE;
}

/** Micro-costs render as whole cents; zero spend stays honest. */
export function formatMicros(micros: number): string {
    return `€${(micros / 1_000_000).toFixed(2)}`;
}
