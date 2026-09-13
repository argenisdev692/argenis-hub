import { useQueryCache } from '@pinia/colada';
import { useAppForm } from '@/common/form';
import { store } from '@/routes/course-scripts';
import {
    courseFormSchema,
    emptyCourseFormValues,
    toCourseStorePayload,
} from '../schemas/courseFormSchema';
import type { CourseUploadLimits } from '../types';
import { COURSES_KEY } from './useCourses';

/**
 * The upload form (US-1).
 *
 * Submits through Inertia, not a Pinia Colada mutation: it carries files and
 * `store()` answers with a redirect to the new course, and the Inertia path is
 * what maps a 422 (e.g. an image-only PDF index) back onto the right field.
 * The list cache is invalidated by hand so the new course shows on return.
 */
export function useCourseForm(limits: CourseUploadLimits) {
    const queryCache = useQueryCache();

    return useAppForm({
        defaultValues: emptyCourseFormValues(),
        schema: courseFormSchema(limits),
        submit: {
            target: store(),
            transform: toCourseStorePayload,
            forceFormData: true,
            successMessage: 'Course uploaded.',
            onSuccess: () => {
                queryCache.invalidateQueries({ key: COURSES_KEY });
            },
        },
    });
}
