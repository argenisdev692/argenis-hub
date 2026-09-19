import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/cv-studio/applications';
import type { StudioApplicationPage } from '../types';

export const STUDIO_APPLICATIONS_KEY = ['studio-applications'];

/**
 * A kanban board cannot page — a card missing from one column is simply
 * invisible. So the board asks for the backend's page ceiling (100) and says
 * so when more exist (`truncated`), instead of silently showing the first 15.
 */
export const BOARD_PAGE_SIZE = 100;

export function useApplications() {
    const { data, ...query } = useQuery<StudioApplicationPage>({
        key: () => [...STUDIO_APPLICATIONS_KEY, { per_page: BOARD_PAGE_SIZE }],
        query: () =>
            httpJson<StudioApplicationPage>(
                index.url({ query: { per_page: BOARD_PAGE_SIZE } }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
    });

    const applications = computed(() => data.value?.data ?? []);
    const total = computed(() => data.value?.total ?? 0);

    return {
        ...query,
        applications,
        total,
        truncated: computed(() => total.value > applications.value.length),
    };
}
