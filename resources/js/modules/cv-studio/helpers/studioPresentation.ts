import type { StudioBand } from '../types';

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

const BAND_LABELS: Record<StudioBand, string> = {
    strong: 'Strong',
    good: 'Good',
    apply: 'Apply',
    consider: 'Consider',
    skip: 'Skip',
};

/** Human label for a score band; unknown values pass through unchanged. */
export function bandLabel(band: string | null): string {
    if (band === null) {
        return 'Unscored';
    }

    return BAND_LABELS[band as StudioBand] ?? band;
}

/** Remote-scope label for filters and badges. */
export function remoteScopeLabel(scope: string | null): string {
    switch (scope) {
        case 'remote_global':
            return 'Remote · Global';
        case 'remote_eu':
            return 'Remote · EU';
        case 'remote_pt_es':
            return 'Remote · PT/ES';
        case 'remote_unclear':
            return 'Remote · Unclear';
        case 'hybrid_local':
            return 'Hybrid / Local';
        default:
            return 'Unknown scope';
    }
}

/** Micro-costs render as whole cents; zero spend stays honest. */
export function formatMicros(micros: number): string {
    return `€${(micros / 1_000_000).toFixed(2)}`;
}
