/**
 * Course Scripts module types.
 *
 * Aliases over `resources/js/generated/generated.d.ts` (produced by
 * `php artisan typescript:transform`), never redefinitions — a renamed backend
 * column must fail the build, not surface as a runtime `undefined`.
 */

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

/**
 * One page of `GET /course-scripts` over JSON.
 *
 * Laravel's flat `LengthAwarePaginator::toArray()` — `index()` answers
 * `response()->json($paginator)`, so the meta keys sit at the top level rather
 * than under the generated Inertia `meta` stub (same as the CVs module).
 */
export type CoursePage = {
    data: CourseListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

/**
 * Soft-delete axis, named after `CourseFilterData::TRASHED_VALUES`.
 * `without` = live only, `only` = the recovery bin, `with` = both.
 */
export type CourseTrashedFilter = 'without' | 'only' | 'with';

/** `CourseFilterData::SORTABLE_FIELDS` — `sort_field` is an allow-list server-side. */
export type CourseSortField = 'created_at' | 'updated_at' | 'title' | 'status';

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
