import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import { index as budgetsIndex, ownRates } from '@/routes/cv-studio/budgets';
import { index as sourcesIndex } from '@/routes/cv-studio/sources';
import type { StudioBudget, StudioOwnRate, StudioSource } from '../types';

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

export function useSources() {
    const { data, ...query } = useQuery<{ data: StudioSource[] }>({
        key: () => ['studio-sources'],
        query: () =>
            httpJson<{ data: StudioSource[] }>(toUrl(sourcesIndex())),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    return { ...query, sources: computed(() => data.value?.data ?? []) };
}
