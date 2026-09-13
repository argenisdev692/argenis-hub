import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import { applyServerErrors, useAppForm } from '@/common/form';
import { HttpError } from '@/lib/http';
import {
    emptyVideoEditFormValues,
    toCreateVideoEditPayload,
    toVideoEditFieldErrors,
    videoEditFormSchema,
} from '../schemas/videoEditFormSchema';
import { useVideoEditMutations } from './useVideoEditMutations';

export type VideoEditFormOptions = {
    /** The dialog's `open` model. The form resets each time it opens. */
    open: Ref<boolean>;
};

/**
 * The create form. Submits through `createVideoEdit` (a JSON + direct-upload
 * flow) rather than `useAppForm`'s Inertia path, because the endpoint answers
 * with JSON and the files must bypass PHP entirely. A 422 on the draft is
 * projected back onto the fields that caused it.
 */
export function useVideoEditForm({ open }: VideoEditFormOptions) {
    const { createVideoEdit } = useVideoEditMutations();

    /** `null` while idle; `{ uploaded, total }` once files start moving. */
    const uploadProgress = ref<{ uploaded: number; total: number } | null>(
        null,
    );

    const form = useAppForm({
        defaultValues: emptyVideoEditFormValues(),
        schema: videoEditFormSchema,
        onSubmit: async (values) => {
            try {
                await createVideoEdit.mutateAsync({
                    payload: toCreateVideoEditPayload(values),
                    sources: values.sources,
                    script:
                        values.mode === 'ai_edit'
                            ? (values.ai_script[0] ?? null)
                            : null,
                    onUploadProgress: (uploaded, total) => {
                        uploadProgress.value = { uploaded, total };
                    },
                });
            } catch (error) {
                // The mutation already toasted; a 422 also names the fields.
                if (
                    error instanceof HttpError &&
                    error.status === 422 &&
                    error.errors
                ) {
                    applyServerErrors(
                        form,
                        toVideoEditFieldErrors(error.errors),
                    );
                }

                return;
            } finally {
                uploadProgress.value = null;
            }

            open.value = false;
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            form.reset(emptyVideoEditFormValues());
        }
    });

    return { form, uploadProgress };
}
