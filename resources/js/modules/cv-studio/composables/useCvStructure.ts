import { useMutation } from '@pinia/colada';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { HttpError, httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { parse } from '@/routes/cv-studio/cvs';

/**
 * Parses a CV (the primary one when no uuid is given) into addressable
 * entry/bullet/skill rows. `parsedUuid` holds the new structure for the
 * confirm step.
 */
export function useParseCvStructure() {
    const parsedUuid = ref<string | null>(null);

    const parseCv = useMutation({
        mutation: (cvUuid: string | null) =>
            httpJson<{ data: { uuid: string } }>(toUrl(parse()), {
                method: 'POST',
                body: cvUuid ? { cv_uuid: cvUuid } : {},
            }),
        onSuccess(response: { data: { uuid: string } }) {
            parsedUuid.value = response.data.uuid;
            toast.success('CV parsed into addressable rows.');
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to parse the CV.',
            );
        },
    });

    return { parseCv, parsedUuid };
}
