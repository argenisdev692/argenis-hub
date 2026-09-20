import { router } from '@inertiajs/vue3';
import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import type { JsonMethod } from '@/lib/http';
import { httpJson, HttpError } from '@/lib/http';
import { update as updateBibleRoute } from '@/routes/course-scripts/bible';
import { store as attachContentRoute } from '@/routes/course-scripts/contents';
import { update as updateNotesRoute } from '@/routes/course-scripts/course-notes';
import { destroy as detachDocumentRoute } from '@/routes/course-scripts/documents';
import { store as attachStyleReferenceRoute } from '@/routes/course-scripts/style-references';
import { update as updateVideoRoute } from '@/routes/course-scripts/videos';
import type { AttachableKind } from '../schemas/courseEditSchemas';
import type {
    CourseBible,
    CourseNotesPayload,
    VideoBriefPayload,
} from '../types';
import { COURSES_KEY } from './useCourses';

/** A Wayfinder route: the URL plus the verb the backend registered. */
type RouteTarget = { url: string; method: string };

function send<TResponse>(
    target: RouteTarget,
    body?: unknown,
): Promise<TResponse> {
    return httpJson<TResponse>(target.url, {
        method: target.method.toUpperCase() as JsonMethod,
        body,
    });
}

/**
 * The course page's edit actions (notes, bible, video briefs, source
 * documents). The page reads the course from its Inertia prop, so a successful
 * write reloads only that prop — no full visit, scroll and open panels stay —
 * and invalidates the list cache, whose rows show the same course.
 *
 * Failures toast here unless they are field validation errors: those are
 * surfaced inline by the form that submitted (`applyHttpErrors`), where a
 * toast would only repeat them.
 */
export function useCourseContentMutations(courseUuid: () => string) {
    const queryCache = useQueryCache();

    function refreshCourse(): void {
        router.reload({ only: ['course'] });
        void queryCache.invalidateQueries({ key: COURSES_KEY });
    }

    function contentMutation<TVariables>(options: {
        mutation: (variables: TVariables) => Promise<unknown>;
        successMessage: string;
        errorMessage: string;
    }) {
        return useMutation({
            mutation: options.mutation,
            onSuccess() {
                toast.success(options.successMessage);
                refreshCourse();
            },
            onError(error) {
                if (error instanceof HttpError && error.errors) {
                    return;
                }

                toast.error(
                    error instanceof HttpError
                        ? error.message
                        : options.errorMessage,
                );
            },
        });
    }

    const updateCourseNotes = contentMutation({
        mutation: (payload: CourseNotesPayload) =>
            send(updateNotesRoute(courseUuid()), payload),
        successMessage: 'Course notes saved.',
        errorMessage: 'Failed to save the course notes.',
    });

    const updateBible = contentMutation({
        mutation: (payload: CourseBible) =>
            send(updateBibleRoute(courseUuid()), payload),
        successMessage: 'Course bible saved.',
        errorMessage: 'Failed to save the course bible.',
    });

    const updateVideoBrief = contentMutation({
        mutation: ({
            videoUuid,
            payload,
        }: {
            videoUuid: string;
            payload: VideoBriefPayload;
        }) =>
            send(updateVideoRoute({ uuid: courseUuid(), videoUuid }), payload),
        successMessage: 'Video brief saved.',
        errorMessage: 'Failed to save the video brief.',
    });

    const attachDocument = contentMutation({
        mutation: ({ kind, body }: { kind: AttachableKind; body: FormData }) =>
            send(
                kind === 'content'
                    ? attachContentRoute(courseUuid())
                    : attachStyleReferenceRoute(courseUuid()),
                body,
            ),
        successMessage: 'Document attached.',
        errorMessage: 'Failed to attach the document.',
    });

    const detachDocument = contentMutation({
        mutation: (documentUuid: string) =>
            send(detachDocumentRoute({ uuid: courseUuid(), documentUuid })),
        successMessage: 'Document removed.',
        errorMessage: 'Failed to remove the document.',
    });

    return {
        updateCourseNotes,
        updateBible,
        updateVideoBrief,
        attachDocument,
        detachDocument,
    };
}
