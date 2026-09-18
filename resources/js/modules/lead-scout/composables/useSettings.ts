import { useQuery } from '@pinia/colada';
import { computed } from 'vue';
import { httpJson } from '@/lib/http';
import { show as showAiSettings } from '@/routes/lead-scout/ai-settings';
import { show as showBudgets } from '@/routes/lead-scout/budgets';
import type { AiSettings, BudgetStatus } from '../types';

/**
 * AI defaults + monthly budgets. Read rarely, invalidated by their own
 * mutations — never mirrored into a store.
 */
export function useAiSettings() {
    const { data, ...query } = useQuery<AiSettings>({
        key: () => ['lead-scout', 'ai-settings'],
        query: () => httpJson<{ data: AiSettings }>(showAiSettings().url).then((response) => response.data),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    return { ...query, settings: computed(() => data.value ?? null) };
}

export function useBudgets() {
    const { data, ...query } = useQuery<BudgetStatus>({
        key: () => ['lead-scout', 'budgets'],
        query: () => httpJson<{ data: BudgetStatus }>(showBudgets().url).then((response) => response.data),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
    });

    return { ...query, budgets: computed(() => data.value ?? null) };
}
