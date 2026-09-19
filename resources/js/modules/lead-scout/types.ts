/**
 * LeadScout module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes and
 * backed enums, so these names track the backend automatically. Request
 * DTOs carry `MapOutputName(SnakeCaseMapper)`, so their generated shape IS
 * the snake_case wire payload.
 */

// --- Read models (responses). ---
export type LeadListItem = Modules.LeadScout.Application.DTOs.LeadListItemData;
export type LeadDetail = Modules.LeadScout.Application.DTOs.LeadDetailData;
export type LeadDecisor = Modules.LeadScout.Application.DTOs.ContactData;
export type LeadChannel =
    Modules.LeadScout.Application.DTOs.LeadDetailData['channels'][number];
export type LeadOutreach = Modules.LeadScout.Application.DTOs.OutreachData;
export type LeadScoreReason =
    Modules.LeadScout.Application.DTOs.LeadDetailData['reasons'][number];
export type AiSettings = Modules.LeadScout.Application.DTOs.AiSettingsData;
export type AiCatalogOption =
    AiSettings['purposes'][string]['options'][number];
export type BudgetStatus = Modules.LeadScout.Application.DTOs.BudgetStatusData;

// --- Enums. ---
export type LeadTier = Modules.LeadScout.Domain.Enums.Tier | null;
export type ReplyOutcome = Modules.LeadScout.Domain.Enums.ReplyOutcome;
/** `UpdateChannelData` accepts only these two; `used` is set by the pipeline. */
export type ChannelStatusUpdate = Exclude<
    Modules.LeadScout.Domain.Enums.ChannelStatus,
    'used'
>;

/**
 * One page of the bandeja, exactly as `LeadController::index()` serializes it:
 * a hand-built `{ data, meta }` envelope with only `current_page`, `per_page`
 * and `total` (asserted by `BandejaTest` as `meta.total`). `useLeads` derives
 * `last_page` / `from` / `to` from those three.
 *
 * Neither the generated `Illuminate.LengthAwarePaginator` (it expects
 * `links` + a full `meta`) nor Laravel's flat `toArray()` shape matches it.
 */
export type LeadPage = {
    data: LeadListItem[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
    };
};

type LeadFilterData = Modules.LeadScout.Application.DTOs.LeadFilterData;
type LeadListFilterKey =
    'tier' | 'country' | 'company_type' | 'signal_type' | 'stage' | 'origin';

/**
 * Bandeja filter state. Keys come from `LeadFilterData`, so a renamed backend
 * filter breaks the build instead of silently dropping out of the query.
 * Multi-selects are never null client-side (empty = no filter), and `page`
 * is the paginator's own query param, not part of the DTO.
 */
export type LeadFilters = {
    [K in LeadListFilterKey]: NonNullable<LeadFilterData[K]>;
} & Pick<LeadFilterData, 'needs_research' | 'date_from' | 'date_to'> & {
        search: NonNullable<LeadFilterData['search']>;
        page: number;
        per_page: NonNullable<LeadFilterData['per_page']>;
    };

// --- Request payloads (the backend accepts omitted keys as null). ---
export type DraftPayload = Partial<
    Modules.LeadScout.Application.DTOs.GenerateDraftData
>;
export type StagePayload = Partial<
    Modules.LeadScout.Application.DTOs.UpdateOutreachData
>;

type UpsertContactData = Modules.LeadScout.Application.DTOs.UpsertContactData;
export type ContactPayload = Pick<
    UpsertContactData,
    'full_name' | 'role_title' | 'role_category'
> &
    Partial<
        Pick<
            UpsertContactData,
            'published_email' | 'public_profile_url' | 'is_primary'
        >
    >;

type UpdateAiSettingsData =
    Modules.LeadScout.Application.DTOs.UpdateAiSettingsData;
export type AiSettingsPayload = Pick<
    UpdateAiSettingsData,
    'purpose' | 'provider' | 'model'
> &
    Partial<Pick<UpdateAiSettingsData, 'fallback_provider' | 'fallback_model'>>;

export type SuppressPayload = Modules.LeadScout.Application.DTOs.SuppressData;
export type BudgetLimit =
    Modules.LeadScout.Application.DTOs.UpdateBudgetsData['budgets'][number];
