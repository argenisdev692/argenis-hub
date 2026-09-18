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
