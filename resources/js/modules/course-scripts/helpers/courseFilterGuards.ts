import type {
    CourseSortField,
    CourseStatus,
    CourseTrashedFilter,
} from '../types';
import { COURSE_SORT_FIELDS, COURSE_TRASHED_VALUES } from '../types';
import { COURSE_STATUSES_ORDERED } from './coursePresentation';

/**
 * Runtime guards for the filter axes. Values arrive untyped from the URL,
 * Inertia's history state and `FilterSelect`, so each is narrowed here once
 * and shared by the list composable and the index page.
 */

export function isCourseStatus(value: unknown): value is CourseStatus {
    return (
        typeof value === 'string' &&
        (COURSE_STATUSES_ORDERED as readonly string[]).includes(value)
    );
}

export function isCourseSortField(value: unknown): value is CourseSortField {
    return (
        typeof value === 'string' &&
        (COURSE_SORT_FIELDS as readonly string[]).includes(value)
    );
}

export function isCourseTrashedFilter(
    value: unknown,
): value is CourseTrashedFilter {
    return (
        typeof value === 'string' &&
        (COURSE_TRASHED_VALUES as readonly string[]).includes(value)
    );
}
