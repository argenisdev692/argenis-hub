/**
 * CV Studio module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically.
 */

import type { PaginatedPage } from '@/common/table';

export type StudioPosting =
    Modules.CvJobStudio.Application.DTOs.StudioPostingData;
export type StudioPostingFilter =
    Modules.CvJobStudio.Application.DTOs.StudioPostingFilterData;
export type StudioProfile =
    Modules.CvJobStudio.Application.DTOs.StudioProfileData;
export type StudioScore = Modules.CvJobStudio.Application.DTOs.StudioScoreData;
export type StudioScoreInput =
    Modules.CvJobStudio.Application.DTOs.ScorePostingInputData;
export type StudioBand = Modules.CvJobStudio.Domain.Enums.ScoreBand;
export type StudioRemoteScope = Modules.CvJobStudio.Domain.Enums.RemoteScope;

export type StudioPostingPage = PaginatedPage<StudioPosting>;

/** The soft-delete axis (`StudioPostingFilterData::rules()` → `status`). */
export type StudioStatusFilter = 'all' | 'active' | 'suspended';

/** The pipeline axis (`StudioPostingFilterData::STAGES`). */
export type StudioPostingStage =
    'new' | 'saved' | 'applied' | 'dismissed' | 'skipped';

/** `StudioPostingFilterData::SORTABLE`. */
export type StudioPostingSortField =
    'created_at' | 'title' | 'employer_name' | 'fit';

/**
 * The toolbar's working copy of `StudioPostingFilter`, narrowed to the
 * literal unions the backend `in:` rules accept, plus paging.
 */
export type StudioPostingFilters = {
    search: string;
    status: StudioStatusFilter;
    remote_scope: StudioRemoteScope | '';
    stages: StudioPostingStage[];
    date_from: string | null;
    date_to: string | null;
    sort_field: StudioPostingSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

export type StudioRun = {
    uuid: string;
    status: string;
    candidates_count: number;
    gate_passed_count: number;
    extracted_count: number;
    scored_count: number;
    new_matches_count: number;
    spend_micros: number;
};

export type StudioReference = {
    uuid: string;
    title: string;
    employer_name: string | null;
    canonical_url: string;
    source: string | null;
    created_at: string | null;
};

export type StudioApplication = {
    uuid: string;
    status: string;
    outcome: string;
    applied_at: string | null;
    posting: {
        uuid: string;
        title: string;
        employer_name: string | null;
        status: string;
    } | null;
};

export type StudioBudget = {
    category: string;
    limit_micros: number;
    spent_micros: number;
};

export type StudioOwnRate = {
    bucket: string;
    applications: number;
    positives: number;
    rate: number | null;
    lower: number;
    upper: number;
    gate_passed: boolean;
};
