import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import type { PaginationMeta } from '@/common/table';
import { HttpError, httpJson } from '@/lib/http';
import { exportMethod, index } from '@/routes/cv-studio/versions';
import type { StudioCvVersionPage } from '../types';

export const STUDIO_VERSIONS_KEY = ['studio-versions'];

/** The owner's generated CV versions, paged (Versions page). */
export function useCvVersions() {
    const page = ref(1);
    const perPage = ref(15);

    // A new page size re-slices the list: stay on page 1, not an out-of-range page.
    watch(perPage, () => {
        page.value = 1;
    });

    const { data, ...query } = useQuery<StudioCvVersionPage>({
        key: () => [
            ...STUDIO_VERSIONS_KEY,
            { page: page.value, per_page: perPage.value },
        ],
        query: () =>
            httpJson<StudioCvVersionPage>(
                index.url({
                    query: { page: page.value, per_page: perPage.value },
                }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const meta = computed<PaginationMeta>(() => ({
        current_page: data.value?.current_page ?? 1,
        last_page: data.value?.last_page ?? 1,
        per_page: data.value?.per_page ?? perPage.value,
        from: data.value?.from ?? null,
        to: data.value?.to ?? null,
        total: data.value?.total ?? 0,
    }));

    return {
        ...query,
        versions: computed(() => data.value?.data ?? []),
        meta,
        page,
        perPage,
    };
}

export type CvExportFormat = 'docx' | 'pdf';

type CvExportResponse = {
    data: {
        uuid: string;
        verified: boolean;
        download_url: string;
        download_name: string;
    };
};

/**
 * Generates a version's DOCX/PDF, then downloads the 5-minute signed R2 URL
 * the backend returns through a temporary anchor carrying the ATS-friendly
 * suggested filename (`Firstname-Lastname-Resume.pdf`). The export endpoint
 * answers JSON, so a plain `<a download>` to it saved that JSON instead of
 * the document — the anchor targets the signed URL, not the endpoint.
 */
export function useCvVersionExport() {
    const download = useMutation({
        mutation: ({
            uuid,
            format,
        }: {
            uuid: string;
            format: CvExportFormat;
        }) =>
            httpJson<CvExportResponse>(
                exportMethod.url(uuid, { query: { format } }),
            ),
        onSuccess(response: CvExportResponse) {
            if (!response.data.verified) {
                toast.warning(
                    'Exported, but the text could not be re-extracted — check it in an ATS preview.',
                );
            }

            const anchor = document.createElement('a');
            anchor.href = response.data.download_url;
            anchor.download = response.data.download_name;
            anchor.rel = 'noopener noreferrer';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to export the version.',
            );
        },
    });

    return { download };
}

type PromoteResponse = {
    data: { uuid: string };
};

/**
 * The "set as primary" confirmation: promotes a version to a new canonical
 * CV row (supersede, never overwrite) and re-parses it for RAG. Invalidates
 * both the versions list and the CV library cache so the new primary shows
 * up without a reload.
 */
export function usePromoteVersion() {
    const queryCache = useQueryCache();

    const promote = useMutation({
        mutation: ({ uuid, title }: { uuid: string; title?: string }) =>
            httpJson<PromoteResponse>(`/cv-studio/versions/${uuid}/promote`, {
                method: 'POST',
                body: title ? { title } : {},
            }),
        onSuccess() {
            toast.success('Version promoted — it is now the primary CV.');
            queryCache.invalidateQueries({ key: STUDIO_VERSIONS_KEY });
            queryCache.invalidateQueries({ key: ['cvs'] });
        },
        onError(error: unknown) {
            toast.error(
                error instanceof HttpError
                    ? error.message
                    : 'Failed to promote the version.',
            );
        },
    });

    return { promote };
}
