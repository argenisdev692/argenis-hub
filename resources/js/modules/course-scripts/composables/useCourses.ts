import { router } from '@inertiajs/vue3';
import { useQuery } from '@pinia/colada';
import { computed, ref, watch } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/course-scripts';
import { buildCourseListQueryParams } from '../helpers/buildCourseQueryParams';
import { COURSE_STATUSES_ORDERED } from '../helpers/coursePresentation';
import type {
    CourseFilters,
    CoursePage,
    CourseSortField,
    CourseStatus,
    CourseTrashedFilter,
} from '../types';
import { COURSE_SORT_FIELDS, COURSE_TRASHED_VALUES } from '../types';

/** The key every course mutation invalidates. */
export const COURSES_KEY = ['course-scripts'];

/** Inertia history-state key that keeps filters across back/forward visits. */
const REMEMBER_KEY = 'course-scripts:filters';

/** `CourseFilterData` rule `search: max:100` — longer input would 422 the list. */
export const COURSE_SEARCH_MAX_LENGTH = 100;

export function defaultCourseFilters(): CourseFilters {
    return {
        search: '',
        status: '',
        trashed: 'without',
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

function isCourseStatus(value: unknown): value is CourseStatus {
    return (
        typeof value === 'string' &&
        (COURSE_STATUSES_ORDERED as readonly string[]).includes(value)
    );
}

function isSortField(value: unknown): value is CourseSortField {
    return (
        typeof value === 'string' &&
        (COURSE_SORT_FIELDS as readonly string[]).includes(value)
    );
}

function isTrashedFilter(value: unknown): value is CourseTrashedFilter {
    return (
        typeof value === 'string' &&
        (COURSE_TRASHED_VALUES as readonly string[]).includes(value)
    );
}

function toPage(value: unknown, fallback: number): number {
    return typeof value === 'number' && Number.isFinite(value) && value >= 1
        ? Math.floor(value)
        : fallback;
}

/**
 * Filters remembered in Inertia's history state, so leaving for a course page
 * and coming back (back button, breadcrumb) restores the table as it was. A
 * shared link still wins: `useUrlSyncedFilters` hydrates over this right after
 * for every param the URL actually carries.
 */
function restoreFilters(): CourseFilters {
    const defaults = defaultCourseFilters();

    if (typeof window === 'undefined') {
        return defaults;
    }

    const remembered = router.restore<Partial<CourseFilters>>(REMEMBER_KEY);

    if (!remembered) {
        return defaults;
    }

    return {
        search:
            typeof remembered.search === 'string'
                ? remembered.search
                : defaults.search,
        status: isCourseStatus(remembered.status)
            ? remembered.status
            : defaults.status,
        trashed: isTrashedFilter(remembered.trashed)
            ? remembered.trashed
            : defaults.trashed,
        date_from:
            typeof remembered.date_from === 'string'
                ? remembered.date_from
                : defaults.date_from,
        date_to:
            typeof remembered.date_to === 'string'
                ? remembered.date_to
                : defaults.date_to,
        sort_field: isSortField(remembered.sort_field)
            ? remembered.sort_field
            : defaults.sort_field,
        sort_order:
            remembered.sort_order === 1 || remembered.sort_order === -1
                ? remembered.sort_order
                : defaults.sort_order,
        page: toPage(remembered.page, defaults.page),
        per_page: toPage(remembered.per_page, defaults.per_page),
    };
}

/**
 * The course list as server state.
 *
 * `GET /course-scripts` serves the Inertia page and this JSON query from one
 * action (`expectsJson()`); reading it over XHR is what gives the table
 * `placeholderData` and a cache the mutations can invalidate. Rows are scoped
 * to the owner server-side (`ownedBy`), so `VIEW_ANY_COURSE_SCRIPTS` alone
 * never reveals another author's courses.
 */
export function useCourses() {
    const filters = ref<CourseFilters>(restoreFilters());

    if (typeof window !== 'undefined') {
        watch(
            filters,
            (value) => {
                router.remember({ ...value }, REMEMBER_KEY);
            },
            { deep: true },
        );
    }

    const queryParams = computed(() =>
        buildCourseListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<CoursePage>({
        key: () => [...COURSES_KEY, { ...queryParams.value }],
        query: () =>
            httpJson<CoursePage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const courses = computed(() => data.value?.data ?? []);

    const meta = computed<PaginationMeta>(() => ({
        current_page: data.value?.current_page ?? 1,
        last_page: data.value?.last_page ?? 1,
        per_page: data.value?.per_page ?? filters.value.per_page,
        from: data.value?.from ?? null,
        to: data.value?.to ?? null,
        total: data.value?.total ?? 0,
    }));

    return { ...query, courses, meta, filters };
}
