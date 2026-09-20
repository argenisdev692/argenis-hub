/**
 * Course Scripts module types.
 *
 * Aliases over `resources/js/generated/generated.d.ts` (produced by
 * `php artisan typescript:transform`), never redefinitions — a renamed backend
 * column must fail the build, not surface as a runtime `undefined`.
 */

import type { PaginatedPage } from '@/common/table';

/** One row of the list, as `CourseListItemData` serializes it. */
export type CourseListItem =
    Modules.CourseScripts.Application.DTOs.CourseListItemData;

/** The course page prop, as `CourseDetailData` serializes it. */
export type CourseDetail =
    Modules.CourseScripts.Application.DTOs.CourseDetailData;

export type CourseVideo = Modules.CourseScripts.Application.DTOs.VideoBriefData;

export type CourseBlock = Modules.CourseScripts.Application.DTOs.BlockData;

export type CourseDocument =
    Modules.CourseScripts.Application.DTOs.SourceDocumentData;

/** Generation lifecycle — independent of soft-delete. */
export type CourseStatus = Modules.CourseScripts.Domain.Enums.CourseStatus;

export type VideoScriptStatus =
    Modules.CourseScripts.Domain.Enums.VideoScriptStatus;

export type SourceDocumentKind =
    Modules.CourseScripts.Domain.Enums.SourceDocumentKind;

export type CourseBible =
    Modules.CourseScripts.Application.DTOs.CourseBibleData;

export type BibleOrganisation =
    Modules.CourseScripts.Application.DTOs.BibleOrganisationData;

export type BibleCharacter =
    Modules.CourseScripts.Application.DTOs.BibleCharacterData;

/** `PUT /course-scripts/{uuid}/videos/{videoUuid}` body. */
export type VideoBriefPayload =
    Modules.CourseScripts.Application.DTOs.UpdateVideoBriefData;

/** `PUT /course-scripts/{uuid}/course-notes` body. */
export type CourseNotesPayload =
    Modules.CourseScripts.Application.DTOs.UpdateCourseNotesData;

/**
 * One page of `GET /course-scripts` over JSON — the table kit's flat
 * `response()->json($paginator)` shape (meta keys beside `data`).
 */
export type CoursePage = PaginatedPage<CourseListItem>;

/**
 * Soft-delete axis, named after `CourseFilterData::TRASHED_VALUES`.
 * `without` = live only, `only` = the recovery bin, `with` = both.
 */
export type CourseTrashedFilter = 'without' | 'only' | 'with';

/** `CourseFilterData::TRASHED_VALUES`, for client-side guards (URL restore). */
export const COURSE_TRASHED_VALUES: readonly CourseTrashedFilter[] = [
    'without',
    'only',
    'with',
];

/** `CourseFilterData::SORTABLE_FIELDS` — `sort_field` is an allow-list server-side. */
export type CourseSortField = 'created_at' | 'updated_at' | 'title' | 'status';

/** The sortable fields, for client-side guards (sort mapping, URL restore). */
export const COURSE_SORT_FIELDS: readonly CourseSortField[] = [
    'created_at',
    'updated_at',
    'title',
    'status',
];

/**
 * The query params `GET /course-scripts` and `/course-scripts/export` accept.
 * Snake_case: `CourseFilterData` maps input names with `SnakeCaseMapper`.
 */
export type CourseFilters = {
    search: string;
    /** Empty string means "any status". */
    status: '' | CourseStatus;
    trashed: CourseTrashedFilter;
    date_from: string | null;
    date_to: string | null;
    sort_field: CourseSortField;
    /** `1` ascending, `-1` descending. */
    sort_order: 1 | -1;
    page: number;
    per_page: number;
};

/** Upload limits `CourseController::create()` passes from `config/course-scripts.php`. */
export type CourseUploadLimits = {
    max_kb: number;
    max_content_files: number;
    allowed_extensions: string[];
};
