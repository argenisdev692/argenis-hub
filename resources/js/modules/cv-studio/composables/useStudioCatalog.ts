import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { index as budgetsIndex, ownRates } from '@/routes/cv-studio/budgets';
import { index as sourcesIndex } from '@/routes/cv-studio/sources';
import type { StudioBudget, StudioOwnRate } from '../types';

export function useBudgets() {
    const { data, ...query } = useQuery<{ data: StudioBudget[] }>({
        key: () => ['studio-budgets'],
        query: () => httpJson<{ data: StudioBudget[] }>(toUrl(budgetsIndex())),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
    });

    return { ...query, budgets: computed(() => data.value?.data ?? []) };
}

export function useOwnRates() {
    const { data, ...query } = useQuery<{ data: StudioOwnRate[] }>({
        key: () => ['studio-own-rates'],
        query: () => httpJson<{ data: StudioOwnRate[] }>(toUrl(ownRates())),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    return { ...query, rates: computed(() => data.value?.data ?? []) };
}

export type StudioSourceRow = {
    uuid: string;
    name: string;
    kind: string;
    tier: number;
    layer: string;
    status: string;
    access_mode: string;
    resolution_tier: number;
};

export function useSources() {
    const { data, ...query } = useQuery<{ data: StudioSourceRow[] }>({
        key: () => ['studio-sources'],
        query: () =>
            httpJson<{ data: StudioSourceRow[] }>(toUrl(sourcesIndex())),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    return { ...query, sources: computed(() => data.value?.data ?? []) };
}
