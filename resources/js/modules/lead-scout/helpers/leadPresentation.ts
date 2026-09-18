import type { BadgeVariants } from '@/components/ui/badge';

export const LEAD_TIER_OPTIONS: { value: string; label: string }[] = [
    { value: 'A', label: 'Tier A' },
    { value: 'B', label: 'Tier B' },
    { value: 'C', label: 'Tier C' },
    { value: 'discarded', label: 'Discarded' },
];

export const LEAD_ORIGIN_OPTIONS: { value: string; label: string }[] = [
    { value: 'job_posting', label: 'Job posting' },
    { value: 'discovery', label: 'Discovery' },
    { value: 'import', label: 'Import' },
    { value: 'manual', label: 'Manual' },
];

export const LEAD_TYPE_OPTIONS: { value: string; label: string }[] = [
    { value: 'software_agency', label: 'Software agency' },
    { value: 'consultancy', label: 'Consultancy' },
    { value: 'product_company', label: 'Product' },
    { value: 'recruiter', label: 'Recruiter' },
    { value: 'large_outsourcer', label: 'Large outsourcer' },
    { value: 'other', label: 'Other' },
];

/**
 * Country facet — mirrors the discovery waves in `config/lead-scout.php`.
 * If a wave gains a country there, add it here so the facet stays complete.
 */
export const LEAD_COUNTRY_OPTIONS: { value: string; label: string }[] = [
    { value: 'PT', label: 'Portugal' },
    { value: 'ES', label: 'Spain' },
    { value: 'IE', label: 'Ireland' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'NL', label: 'Netherlands' },
    { value: 'DE', label: 'Germany' },
    { value: 'BE', label: 'Belgium' },
    { value: 'FR', label: 'France' },
    { value: 'IT', label: 'Italy' },
    { value: 'AT', label: 'Austria' },
    { value: 'DK', label: 'Denmark' },
    { value: 'SE', label: 'Sweden' },
    { value: 'PL', label: 'Poland' },
    { value: 'CZ', label: 'Czechia' },
    { value: 'GR', label: 'Greece' },
    { value: 'LT', label: 'Lithuania' },
    { value: 'LV', label: 'Latvia' },
    { value: 'EE', label: 'Estonia' },
    { value: 'RO', label: 'Romania' },
    { value: 'FI', label: 'Finland' },
    { value: 'CH', label: 'Switzerland' },
    { value: 'NO', label: 'Norway' },
    { value: 'US', label: 'United States' },
    { value: 'CA', label: 'Canada' },
    { value: 'AR', label: 'Argentina' },
    { value: 'UY', label: 'Uruguay' },
    { value: 'CL', label: 'Chile' },
    { value: 'CO', label: 'Colombia' },
    { value: 'MX', label: 'Mexico' },
    { value: 'UA', label: 'Ukraine' },
    { value: 'AU', label: 'Australia' },
    { value: 'NZ', label: 'New Zealand' },
];

/** Signal-dimension facet — mirrors `SignalDimension` values. */
export const LEAD_SIGNAL_OPTIONS: { value: string; label: string }[] = [
    { value: 'technical', label: 'Technical' },
    { value: 'commercial', label: 'Commercial' },
    { value: 'recurrent', label: 'Recurrent' },
    { value: 'vitality', label: 'Vitality' },
    { value: 'communication', label: 'Communication' },
    { value: 'geo_contract', label: 'Geo + contract' },
    { value: 'remote', label: 'Remote' },
];

export const NEEDS_RESEARCH_OPTIONS: { value: string; label: string }[] = [
    { value: '1', label: 'Needs research' },
    { value: '0', label: 'Reviewed' },
];

export const OUTREACH_STAGE_OPTIONS: { value: string; label: string }[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'ready', label: 'Ready' },
    { value: 'sent', label: 'Sent' },
    { value: 'replied', label: 'Replied' },
    { value: 'positive', label: 'Positive' },
    { value: 'call', label: 'Call' },
    { value: 'trial', label: 'Trial' },
    { value: 'won', label: 'Won' },
    { value: 'recurrent', label: 'Recurrent' },
    { value: 'lost', label: 'Lost' },
    { value: 'do_not_contact', label: 'Do not contact' },
];

/** Badge variant per tier — shadcn variants only, no hardcoded colors. */
export function tierSeverity(
    tier: string | null,
): NonNullable<BadgeVariants['variant']> {
    switch (tier) {
        case 'A':
            return 'default';
        case 'B':
            return 'secondary';
        case 'C':
            return 'outline';
        case 'discarded':
            return 'destructive';
        default:
            return 'outline';
    }
}

export function formatScore(value: number | null): string {
    return value === null ? '—' : String(value);
}
