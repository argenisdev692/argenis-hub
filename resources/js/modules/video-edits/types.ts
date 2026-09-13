/**
 * Video Edits module types.
 *
 * Aliases over the generated declarations, never redefinitions —
 * `resources/js/generated/generated.d.ts` is emitted by
 * `php artisan typescript:transform` from the Spatie `Data` classes.
 *
 * An edit is an immutable job: there is no update payload and no restore.
 * Deletion is permanent by decision (spec 001-video-edit Q2/Q5), so rows carry
 * no `deleted_at` and the list has no "Deleted" view.
 */

export type VideoEditListItem =
    Modules.VideoEdits.Application.DTOs.VideoEditListItemData;
export type VideoEditDetail =
    Modules.VideoEdits.Application.DTOs.VideoEditDetailData;
export type VideoEditSource =
    Modules.VideoEdits.Application.DTOs.VideoEditSourceData;
export type AppliedCut = Modules.VideoEdits.Application.DTOs.AppliedCutData;
export type CutDecision = Modules.VideoEdits.Application.DTOs.CutDecisionData;
export type CreateVideoEditPayload =
    Modules.VideoEdits.Application.DTOs.CreateVideoEditData;
export type CreatedVideoEdit =
    Modules.VideoEdits.Application.DTOs.CreatedVideoEditData;
export type UploadTarget = Modules.VideoEdits.Application.DTOs.UploadTargetData;
export type DownloadUrl = Modules.VideoEdits.Application.DTOs.DownloadUrlData;
export type BulkDeletedVideoEdits =
    Modules.VideoEdits.Application.DTOs.BulkDeletedVideoEditsData;

export type VideoEditStatus = Modules.VideoEdits.Domain.Enums.VideoEditStatus;
export type VideoEditMode = Modules.VideoEdits.Domain.Enums.VideoEditMode;
export type ProcessingStage = Modules.VideoEdits.Domain.Enums.ProcessingStage;
export type SpeechCategory = Modules.VideoEdits.Domain.Enums.SpeechCategory;
export type CutReason = Modules.VideoEdits.Domain.Enums.CutReason;

/** `draft` is internal and never listed (D17), so it is not a filter value. */
export type ListedVideoEditStatus = Exclude<VideoEditStatus, 'draft'>;

/** Sortable columns — mirrors `VideoEditFilterData::SORTABLE_FIELDS`. */
export type VideoEditSortField =
    | 'created_at'
    | 'completed_at'
    | 'status'
    | 'mode'
    | 'final_duration_ms'
    | 'applied_cut_count';

/**
 * One page of the list as `VideoEditController::index()` serializes it —
 * Laravel's flat `LengthAwarePaginator::toArray()`, same reasoning as `CvPage`.
 */
export type VideoEditPage = {
    data: VideoEditListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

/**
 * The query params `GET /data/admin/video-edits` accepts, snake_case because
 * `VideoEditFilterData` maps its input names. Empty string means "no filter".
 */
export type VideoEditFilters = {
    search: string;
    status: '' | ListedVideoEditStatus;
    mode: '' | VideoEditMode;
    date_from: string | null;
    date_to: string | null;
    sort_field: VideoEditSortField;
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};
