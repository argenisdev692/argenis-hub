import { useMutation, useQueryCache } from '@pinia/colada';
import type { EntryKey } from '@pinia/colada';
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
import type { CoursePage } from '../types';
import { COURSES_KEY } from './useCourses';

type BulkResult = { data: { affected: number } };

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

/**
 * One snapshot per cached list entry, so a failed mutation rolls back exactly
 * what it overwrote — never a concurrent mutation that landed in between.
 */
type ListSnapshot = {
    key: EntryKey;
    previous: CoursePage | undefined;
    optimistic: CoursePage | undefined;
};

type MutationContext = {
    snapshots: ListSnapshot[];
};

type OptimisticPatch<TVariables> = (
    page: CoursePage | undefined,
    variables: TVariables,
    key: EntryKey,
) => CoursePage | undefined;

/** A page is the recovery-bin view when its cached params say `trashed: only`. */
function isDeletedOnlyView(key: EntryKey): boolean {
    const params = key[1];

    return (
        typeof params === 'object' &&
        params !== null &&
        !Array.isArray(params) &&
        (params as { trashed?: unknown }).trashed === 'only'
    );
}

/** Drop the rows from every cached page (correct in all three trash views). */
function withoutUuids(
    page: CoursePage | undefined,
    uuids: readonly string[],
): CoursePage | undefined {
    if (!page) {
        return page;
    }

    const kept = page.data.filter((row) => !uuids.includes(row.uuid));

    if (kept.length === page.data.length) {
        return page;
    }

    return {
        ...page,
        data: kept,
        total: Math.max(0, page.total - (page.data.length - kept.length)),
    };
}

/**
 * Flip restored rows to live everywhere; in the recovery bin they no longer
 * belong, so drop them there. The `onSettled` refetch reconciles counts.
 */
function withRestoredFlag(
    page: CoursePage | undefined,
    uuids: readonly string[],
    key: EntryKey,
): CoursePage | undefined {
    if (!page) {
        return page;
    }

    if (!page.data.some((row) => uuids.includes(row.uuid))) {
        return page;
    }

    if (isDeletedOnlyView(key)) {
        return withoutUuids(page, uuids);
    }

    return {
        ...page,
        data: page.data.map((row) =>
            uuids.includes(row.uuid) ? { ...row, deleted_at: null } : row,
        ),
    };
}

/**
 * Row and bulk actions of the list. Every endpoint has a JSON branch
 * (`expectsJson()`), so bulk toasts report the server's `affected` count —
 * rows already moved by someone else are not counted twice.
 *
 * Each mutation applies its change to every cached page up front (`onMutate`),
 * rolls back only entries that still hold that exact optimistic value
 * (`onError` — an unguarded rollback would discard a concurrent mutation), and
 * refetches in `onSettled`: a failed call may still have moved rows (a
 * concurrent action), and the refetch shows the list's real state.
 */
export function useCourseMutations() {
    const queryCache = useQueryCache();

    /** Snapshot every cached page, write the optimistic rows, freeze refetch. */
    function applyOptimistic<TVariables>(
        patch: OptimisticPatch<TVariables>,
        variables: TVariables,
    ): MutationContext {
        const snapshots = queryCache
            .getEntries({ key: COURSES_KEY })
            .map((entry) => {
                const previous = queryCache.getQueryData<CoursePage>(entry.key);
                const optimistic = patch(previous, variables, entry.key);

                queryCache.setQueryData(entry.key, optimistic);

                return { key: entry.key, previous, optimistic };
            });

        // A stale in-flight response must not overwrite the optimistic rows.
        queryCache.cancelQueries({ key: COURSES_KEY });

        return { snapshots };
    }

    /** Restore snapshots, but only where no newer value landed since. */
    function rollback(context: MutationContext | undefined): void {
        if (!context) {
            return;
        }

        for (const snapshot of context.snapshots) {
            if (
                queryCache.getQueryData(snapshot.key) === snapshot.optimistic
            ) {
                queryCache.setQueryData(snapshot.key, snapshot.previous);
            }
        }
    }

    /** Narrow the mutation context back to our snapshots (untrusted shape). */
    function toMutationContext(context: unknown): MutationContext | undefined {
        if (
            typeof context === 'object' &&
            context !== null &&
            'snapshots' in context &&
            Array.isArray(
                (context as { snapshots: unknown }).snapshots,
            )
        ) {
            return context as MutationContext;
        }

        return undefined;
    }

    /** One mutation shape for all four actions — only the call and copy differ. */
    function courseMutation<TVariables, TResult>(options: {
        mutation: (variables: TVariables) => Promise<TResult>;
        successMessage: (result: TResult) => string;
        errorMessage: string;
        optimistic?: OptimisticPatch<TVariables>;
    }) {
        return useMutation({
            mutation: options.mutation,
            onMutate(variables) {
                if (!options.optimistic) {
                    return undefined;
                }

                return applyOptimistic(options.optimistic, variables);
            },
            onError(error, _variables, context) {
                rollback(toMutationContext(context));
                toast.error(errorMessage(error, options.errorMessage));
            },
            onSuccess(result: TResult) {
                toast.success(options.successMessage(result));
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
        optimistic: (page, uuid) => withoutUuids(page, [uuid]),
    });

    const restoreCourse = courseMutation({
        mutation: (uuid: string) =>
            httpJson<unknown>(toUrl(restore(uuid)), { method: 'POST' }),
        successMessage: () => 'Course restored.',
        errorMessage: 'Failed to restore the course.',
        optimistic: (page, uuid, key) => withRestoredFlag(page, [uuid], key),
    });

    const bulkDeleteCourses = courseMutation({
        mutation: (uuids: string[]) => postBulk(bulkDelete, uuids),
        successMessage: (affected) =>
            `${pluralize(affected, 'course', 'courses')} deleted.`,
        errorMessage: 'Failed to delete the selected courses.',
        optimistic: (page, uuids) => withoutUuids(page, uuids),
    });

    const bulkRestoreCourses = courseMutation({
        mutation: (uuids: string[]) => postBulk(bulkRestore, uuids),
        successMessage: (affected) =>
            `${pluralize(affected, 'course', 'courses')} restored.`,
        errorMessage: 'Failed to restore the selected courses.',
        optimistic: (page, uuids, key) => withRestoredFlag(page, uuids, key),
    });

    return {
        deleteCourse,
        restoreCourse,
        bulkDeleteCourses,
        bulkRestoreCourses,
    };
}
