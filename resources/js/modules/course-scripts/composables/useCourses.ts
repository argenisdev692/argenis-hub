import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/course-scripts';
import { buildCourseListQueryParams } from '../helpers/buildCourseQueryParams';
import type { CourseFilters, CoursePage } from '../types';

/** The key every course mutation invalidates. */
export const COURSES_KEY = ['course-scripts'];

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
    const filters = ref<CourseFilters>(defaultCourseFilters());

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
