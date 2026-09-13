import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
} from '@/routes/course-scripts';
import { pluralize } from '../helpers/coursePresentation';
import { COURSES_KEY } from './useCourses';

type BulkResult = { data: { affected: number } };

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

/**
 * Row and bulk actions of the list. Every endpoint has a JSON branch
 * (`expectsJson()`), so bulk toasts report the server's `affected` count —
 * rows already moved by someone else are not counted twice.
 *
 * Invalidation runs in `onSettled`: a failed call may still have moved rows
 * (a concurrent action), and the refetch shows the list's real state.
 */
export function useCourseMutations() {
    const queryCache = useQueryCache();

    /** One mutation shape for all four actions — only the call and copy differ. */
    function courseMutation<TVariables, TResult>(options: {
        mutation: (variables: TVariables) => Promise<TResult>;
        successMessage: (result: TResult) => string;
        errorMessage: string;
    }) {
        return useMutation({
            mutation: options.mutation,
            onSuccess(result: TResult) {
                toast.success(options.successMessage(result));
            },
            onError(error: unknown) {
                toast.error(errorMessage(error, options.errorMessage));
            },
            async onSettled() {
                await queryCache.invalidateQueries({ key: COURSES_KEY });
            },
        });
    }

    async function postBulk(
        route: typeof bulkDelete,
        uuids: string[],
    ): Promise<number> {
        const response = await httpJson<BulkResult>(toUrl(route()), {
            method: 'POST',
            body: { uuids },
        });

        return response.data.affected;
    }

    const deleteCourse = courseMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        successMessage: () => 'Course deleted.',
        errorMessage: 'Failed to delete the course.',
    });

    const restoreCourse = courseMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        successMessage: () => 'Course restored.',
        errorMessage: 'Failed to restore the course.',
    });

    const bulkDeleteCourses = courseMutation({
        mutation: (uuids: string[]) => postBulk(bulkDelete, uuids),
        successMessage: (affected) =>
            `${pluralize(affected, 'course', 'courses')} deleted.`,
        errorMessage: 'Failed to delete the selected courses.',
    });

    const bulkRestoreCourses = courseMutation({
        mutation: (uuids: string[]) => postBulk(bulkRestore, uuids),
        successMessage: (affected) =>
            `${pluralize(affected, 'course', 'courses')} restored.`,
        errorMessage: 'Failed to restore the selected courses.',
    });

    return {
        deleteCourse,
        restoreCourse,
        bulkDeleteCourses,
        bulkRestoreCourses,
    };
}
