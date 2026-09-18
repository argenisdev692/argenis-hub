/**
 * LeadScout module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically.
 */

export type LeadListItem = Modules.LeadScout.Application.DTOs.LeadListItemData;
export type LeadDetail = Modules.LeadScout.Application.DTOs.LeadDetailData;
export type LeadDecisor = Modules.LeadScout.Application.DTOs.ContactData;
export type LeadChannel =
    Modules.LeadScout.Application.DTOs.LeadDetailData['channels'][number];
export type LeadOutreach = Modules.LeadScout.Application.DTOs.OutreachData;
export type LeadScoreReason =
    Modules.LeadScout.Application.DTOs.LeadDetailData['reasons'][number];
export type AiSettings = Modules.LeadScout.Application.DTOs.AiSettingsData;
export type AiCatalogOption = {
    provider: string;
    model: string;
    label: string;
    available: boolean;
    unavailable_reason: string | null;
    est_cost_per_100_usd: number;
    price_expired: boolean;
};
export type BudgetStatus = Modules.LeadScout.Application.DTOs.BudgetStatusData;

export type LeadTier = 'A' | 'B' | 'C' | 'discarded' | null;

export type LeadPage = {
    data: LeadListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type LeadFilters = {
    tier: string[];
    country: string[];
    company_type: string[];
    signal_type: string[];
    stage: string[];
    origin: string[];
    needs_research: boolean | null;
    search: string;
    date_from: string | null;
    date_to: string | null;
    page: number;
    per_page: number;
};

export type DraftPayload = {
    job_posting_id?: string;
    variant?: string;
    language?: string;
    provider?: string;
    model?: string;
};

export type StagePayload = {
    draft_body?: string;
    stage?: string;
    notes?: string;
    contact_channel_id?: string;
    send_medium?: string;
    sender_kind?: string;
    acknowledge_pending_legal?: boolean;
};
