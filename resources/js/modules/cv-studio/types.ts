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


export type StudioRun = Modules.CvJobStudio.Application.DTOs.StudioRunData;
export type StudioReference =
    Modules.CvJobStudio.Application.DTOs.StudioReferenceData;
export type StudioApplication =
    Modules.CvJobStudio.Application.DTOs.StudioApplicationData;
export type StudioBudget = Modules.CvJobStudio.Application.DTOs.StudioBudgetData;
export type StudioOwnRate =
    Modules.CvJobStudio.Application.DTOs.StudioOwnRateData;
export type StudioCvVersion =
    Modules.CvJobStudio.Application.DTOs.StudioCvVersionData;
export type StudioRelation =
    Modules.CvJobStudio.Application.DTOs.StudioSkillRelationData;
export type StudioSource = Modules.CvJobStudio.Application.DTOs.StudioSourceData;

export type StudioApplicationPage = PaginatedPage<StudioApplication>;
export type StudioCvVersionPage = PaginatedPage<StudioCvVersion>;

/** Terminal run statuses — polling stops on either. */
export type StudioRunTerminalStatus = 'finished' | 'failed';
